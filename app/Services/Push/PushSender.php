<?php

namespace App\Services\Push;

use App\Models\PushDevice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only code that talks to Apple's (APNs) and Google's (FCM) push services. Each signs its
 * own short-lived JWT, so no SDK is needed; an unconfigured service sends nothing.
 */
class PushSender
{
    public const SENT = 'sent';

    public const SKIPPED = 'skipped';

    /** The phone no longer has the app (or the token is wrong): forget the device. */
    public const GONE = 'gone';

    /** The Android notification channel the app creates at start-up (nativeApp.js), so phones list "Club notices" instead of a generic one. */
    public const ANDROID_CHANNEL = 'club_notices';

    /**
     * @return self::SENT|self::SKIPPED|self::GONE
     */
    public function send(PushDevice $device, string $title, string $body, ?string $url = null, ?int $badge = null): string
    {
        return $device->platform === 'ios'
            ? $this->sendApns($device->token, $title, $body, $url, $badge)
            : $this->sendFcm($device->token, $title, $body, $url, $badge);
    }

    /** $badge is the number shown on the app icon: the person's unread inbox count. */
    private function sendApns(string $token, string $title, string $body, ?string $url, ?int $badge): string
    {
        $config = config('services.apns');
        if (! $config['key_id'] || ! $config['team_id'] || ! $config['key_path']) {
            return self::SKIPPED;
        }

        $host = $config['production'] ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';

        $response = Http::withOptions(['version' => 2.0])
            ->withToken($this->apnsJwt($config), 'bearer')
            ->withHeaders(['apns-topic' => $config['bundle_id'], 'apns-push-type' => 'alert'])
            ->post("{$host}/3/device/{$token}", [
                'aps' => array_filter(['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default', 'badge' => $badge], fn ($value) => $value !== null),
                'url' => $url,
            ]);

        if ($response->status() === 410 || in_array($response->json('reason'), ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
            return self::GONE;
        }

        return $this->checked($response, 'APNs');
    }

    private function sendFcm(string $token, string $title, string $body, ?string $url, ?int $badge): string
    {
        $path = config('services.fcm.credentials_path');
        if (! $path || ! is_readable($path)) {
            return self::SKIPPED;
        }

        $account = json_decode((string) file_get_contents($path), true);

        $response = Http::withToken($this->fcmAccessToken($account))
            ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    // Left out when empty: FCM wants an object, and an empty PHP array is sent as [].
                    ...($url ? ['data' => ['url' => $url]] : []),
                    // notification_count is what launchers that show a count on the icon use.
                    'android' => ['notification' => ['channel_id' => self::ANDROID_CHANNEL, ...($badge ? ['notification_count' => $badge] : [])]],
                ],
            ]);

        $code = $response->collect('error.details')->pluck('errorCode')->first();
        if ($response->status() === 404 || $code === 'UNREGISTERED') {
            return self::GONE;
        }

        return $this->checked($response, 'FCM');
    }

    /**
     * @return self::SENT
     */
    private function checked(Response $response, string $service): string
    {
        if ($response->failed()) {
            Log::warning("{$service} push failed", ['status' => $response->status(), 'body' => $response->body()]);

            $response->throw();
        }

        return self::SENT;
    }

    /**
     * Apple wants the same token reused for up to an hour, so it is cached for 50 minutes.
     *
     * @param  array<string, mixed>  $config
     */
    private function apnsJwt(array $config): string
    {
        return Cache::remember('push:apns-jwt:'.$config['key_id'], 3000, function () use ($config) {
            $key = openssl_pkey_get_private((string) file_get_contents($config['key_path']));
            if ($key === false) {
                throw new RuntimeException('The APNs key at APNS_KEY_PATH could not be read.');
            }

            $input = $this->base64url(json_encode(['alg' => 'ES256', 'kid' => $config['key_id']]))
                .'.'.$this->base64url(json_encode(['iss' => $config['team_id'], 'iat' => time()]));
            openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256);

            return $input.'.'.$this->base64url(self::derToJose($der));
        });
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function fcmAccessToken(array $account): string
    {
        return Cache::remember('push:fcm-token:'.$account['client_email'], 3000, function () use ($account) {
            $now = time();
            $input = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                .'.'.$this->base64url(json_encode([
                    'iss' => $account['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $account['token_uri'],
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));
            openssl_sign($input, $signature, $account['private_key'], OPENSSL_ALGO_SHA256);

            return (string) Http::asForm()->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $input.'.'.$this->base64url($signature),
            ])->throw()->json('access_token');
        });
    }

    /**
     * OpenSSL signs ES256 as DER (SEQUENCE of two INTEGERs); a JWT needs the two 32-byte numbers side by side.
     */
    public static function derToJose(string $der): string
    {
        $offset = ord($der[1]) & 0x80 ? 3 : 2;
        $parts = '';

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = substr($der, $offset + 2, $length);
            $parts .= str_pad(ltrim($integer, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
