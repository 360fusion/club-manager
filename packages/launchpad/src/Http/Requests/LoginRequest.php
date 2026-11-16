<?php

namespace Launchpad\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\PasswordCheck;
use Launchpad\Accounts\Turnstile;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Models\SsoConnection;

class LoginRequest extends FormRequest
{
    /** Wrong passwords for one address from one place before the security check is asked for, and before sign-in is refused for a while */
    public const CHALLENGE_AFTER = 3;

    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** Whether the sign-in page must show the security check: after a few wrong passwords from this place for this address */
    public static function challengeRequired(Request $request): bool
    {
        $email = $request->input('email', $request->old('email'));

        return is_string($email) && RateLimiter::attempts(self::throttleKeyFor($email, (string) $request->ip())) >= self::CHALLENGE_AFTER;
    }

    /**
     * Checks the password. Returns the account when it has two-factor on (its id is put in the session for the second step and nobody is
     * signed in yet), or null once the person is signed in.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?Model
    {
        $this->ensureIsNotRateLimited();

        if (self::challengeRequired($this)) {
            app(Turnstile::class)->verify($this);
        }

        if (config('launchpad.modules.sso') && SsoConnection::forEmail($this->string('email')->toString())?->enforce) {
            throw ValidationException::withMessages(['email' => __('Your team signs in with single sign-on. Use “Sign in with single sign-on” instead.')]);
        }

        $user = AccountRegistration::findByEmail($this->string('email')->toString());

        // the same answer for a wrong address and a wrong password, and the hash is checked either way so the time says nothing
        $valid = $user ? PasswordCheck::verify($user, $this->string('password')->toString()) : Hash::check($this->string('password')->toString(), Hash::make(Str::random(16)));

        if (! $user || ! $valid) {
            RateLimiter::hit($this->throttleKey(), 300);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if (method_exists($user, 'isClosed') && $user->isClosed()) {
            throw ValidationException::withMessages(['email' => __('This account has been closed.')]);
        }

        if (method_exists($user, 'isSuspended') && $user->isSuspended()) {
            throw ValidationException::withMessages(['email' => __('This account has been suspended. Contact support if you think this is a mistake.')]);
        }

        RateLimiter::clear($this->throttleKey());

        if (app(TwoFactor::class)->isOn($user)) {
            $this->session()->put(['login.id' => $user->getKey(), 'login.remember' => $this->boolean('remember')]);

            return $user;
        }

        Auth::login($user, $this->boolean('remember'));

        return null;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    public function throttleKey(): string
    {
        return self::throttleKeyFor($this->string('email')->toString(), (string) $this->ip());
    }

    private static function throttleKeyFor(string $email, string $ip): string
    {
        return 'login:'.Str::transliterate(AccountRegistration::normalizeEmail($email).'|'.$ip);
    }
}
