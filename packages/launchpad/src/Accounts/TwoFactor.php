<?php

namespace Launchpad\Accounts;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Exceptions\Google2FAException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor authentication in one place, for the security page that sets it up and the sign-in that asks for it: the code from an
 * authenticator app, the recovery codes, and how many wrong answers one account is given.
 */
class TwoFactor
{
    public const RECOVERY_CODES = 8;

    /** Steps of 30 seconds either side of now that still count, for a phone whose clock is a little off */
    private const WINDOW = 1;

    private const MAX_WRONG_ANSWERS = 5;

    private const LOCKOUT_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    /** Whether it is on: a secret that a code from the app has confirmed */
    public function isOn(Model $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** @return list<string> */
    public function newRecoveryCodes(): array
    {
        return array_map(fn () => Str::random(10).'-'.Str::random(10), range(1, self::RECOVERY_CODES));
    }

    /** The QR code an authenticator app scans, as an SVG */
    public function qrCodeSvg(Model $user): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('launchpad.site.name'), $user->email, $user->two_factor_secret);

        return trim((new Writer(new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd)))->writeString($url));
    }

    /**
     * Whether $code is what the person's app shows now. A code that was accepted once is not accepted again, nor is an older one: the
     * step it was made for is remembered for a few minutes, and a lock keeps two requests that arrive together from both being the first.
     */
    public function verifyCode(Model $user, string $code): bool
    {
        $secret = $user->two_factor_secret;
        $code = preg_replace('/\s+/', '', $code);

        if (blank($secret) || ! ctype_digit($code)) {
            return false;
        }

        $key = 'two-factor.last-step.'.$user->getKey();

        $verify = function () use ($secret, $code, $key): bool {
            $baseline = Cache::get($key) ?? ($this->google2fa->getTimestamp() - self::WINDOW - 1);

            try {
                $step = $this->google2fa->verifyKeyNewer($secret, $code, $baseline, self::WINDOW);
            } catch (Google2FAException) {
                return false; // a secret that is not one
            }

            if ($step === false) {
                return false;
            }

            Cache::put($key, $step, now()->addMinutes(5));

            return true;
        };

        return Cache::getStore() instanceof LockProvider
            ? Cache::lock($key.'.lock', 5)->block(5, $verify)
            : $verify();
    }

    /** A new secret starts afresh: what the last one accepted says nothing about it (turning it off and on again must not be refused for a code of the same minute) */
    public function forgetAcceptedSteps(Model $user): void
    {
        Cache::forget('two-factor.last-step.'.$user->getKey());
    }

    /**
     * Whether $given is one of the person's recovery codes, and if it is, that code is spent: it is taken off the list in the same locked
     * transaction that found it, so it cannot be used twice, not even at the same moment.
     */
    public function useRecoveryCode(Model $user, string $given): bool
    {
        $given = trim($given);

        if ($given === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $given): bool {
            $fresh = $user::query()->whereKey($user->getKey())->lockForUpdate()->first();
            $codes = $fresh?->two_factor_recovery_codes;

            if (! is_array($codes)) {
                return false;
            }

            $matched = null;
            foreach ($codes as $index => $code) {
                if (hash_equals((string) $code, $given)) { // every code is compared, so the time says nothing about which
                    $matched = $index;
                }
            }

            if ($matched === null) {
                return false;
            }

            unset($codes[$matched]);
            $fresh->two_factor_recovery_codes = array_values($codes);
            $fresh->save();

            return true;
        });
    }

    public function lockedOut(Model $user): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($user), self::MAX_WRONG_ANSWERS);
    }

    public function lockedOutForSeconds(Model $user): int
    {
        return RateLimiter::availableIn($this->limiterKey($user));
    }

    public function wrongAnswer(Model $user): void
    {
        RateLimiter::hit($this->limiterKey($user), self::LOCKOUT_SECONDS);
    }

    public function rightAnswer(Model $user): void
    {
        RateLimiter::clear($this->limiterKey($user));
    }

    private function limiterKey(Model $user): string
    {
        return 'two-factor:'.$user->getKey();
    }
}
