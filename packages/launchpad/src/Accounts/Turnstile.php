<?php

namespace Launchpad\Accounts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cloudflare Turnstile, the "are you a person" check on the public forms (sign-up, forgot-password, contact). A page puts its widget in
 * and posts the token as cf_turnstile_response; this asks Cloudflare whether it is good.
 *
 * It is on whenever both keys are set, unless TURNSTILE_ENABLED is false on purpose, so a server that has keys cannot have the check off
 * by an unset flag. A check that cannot be made (Cloudflare down, a slow answer) fails the form with a message to try again, and never
 * with a 500; the wait is five seconds at most.
 */
final class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The field the widget's token travels in */
    public const FIELD = 'cf_turnstile_response';

    public static function enabled(): bool
    {
        $config = config('launchpad.turnstile');

        if (blank($config['key'] ?? null) || blank($config['secret'] ?? null)) {
            return false;
        }

        $flag = $config['enabled'] ?? null;

        // not said: on, because the keys are there; said: what it says
        return $flag === null || $flag === '' ? true : filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    /** What a page needs to draw its widget: the public key, or null when the check is off */
    public static function siteKey(): ?string
    {
        return self::enabled() ? (string) config('launchpad.turnstile.key') : null;
    }

    /**
     * Passes when the check is off or the token is good; otherwise the form fails with a message on `turnstile`.
     *
     * @throws ValidationException
     */
    public function verify(Request $request): void
    {
        if (! self::enabled()) {
            return;
        }

        $token = $request->input(self::FIELD);

        if (! is_string($token) || $token === '') {
            $this->fail(__('Please complete the security check, then try again.'));
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('launchpad.turnstile.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile could not be reached', ['error' => $e->getMessage()]);

            $this->fail(__('We could not run the security check just now. Please try again in a moment.'));
        }

        if (! $response->successful()) {
            Log::warning('Turnstile answered with an error', ['status' => $response->status()]);

            $this->fail(__('We could not run the security check just now. Please try again in a moment.'));
        }

        if (! $response->json('success')) {
            // The codes say why (a wrong secret, an expired or used token); who it was for is not written down
            Log::warning('Turnstile verification failed', ['codes' => $response->json('error-codes')]);

            $this->fail(__('The security check failed. Please try again.'));
        }
    }

    /** @throws ValidationException */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['turnstile' => $message]);
    }
}
