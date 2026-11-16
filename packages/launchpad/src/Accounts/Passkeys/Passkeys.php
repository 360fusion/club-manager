<?php

namespace Launchpad\Accounts\Passkeys;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Launchpad\Launchpad;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Throwable;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkeys through spatie/laravel-passkeys' WebAuthn checks, behind Launchpad's own routes (so a closed or suspended account, the rate
 * limits and the sign-in record apply). Every ceremony requires user verification, so a passkey is the device and its lock together, and
 * sign-in with one skips the two-factor step. The options for a ceremony live in the session and are used once.
 */
final class Passkeys
{
    private const REGISTER = 'launchpad.passkey.register';

    private const AUTHENTICATE = 'launchpad.passkey.authenticate';

    /** On when the module is and the app's User implements HasPasskeys (docs/building-on-launchpad.md) */
    public static function enabled(): bool
    {
        return (bool) config('launchpad.modules.passkeys', true) && is_subclass_of(Launchpad::userModel(), HasPasskeys::class);
    }

    public function registrationOptions(HasPasskeys $user): string
    {
        $options = app(RegisterOptions::class)->execute($user);
        Session::put(self::REGISTER, $options);

        return $options;
    }

    /** @throws Throwable when the browser's answer does not check out */
    public function store(HasPasskeys $user, string $credential, string $name, string $host): Passkey
    {
        $options = Session::pull(self::REGISTER) ?? throw new \InvalidArgumentException('No passkey was being added.');

        return app(StorePasskeyAction::class)->execute($user, $credential, $options, $host, ['name' => $name]);
    }

    public function authenticationOptions(): string
    {
        $options = Serializer::make()->toJson(new PublicKeyCredentialRequestOptions(
            challenge: Str::random(32),
            rpId: Config::getRelyingPartyId(),
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        ));
        Session::put(self::AUTHENTICATE, $options);

        return $options;
    }

    /** The passkey the browser signed with, once its signature, challenge, origin and user verification check out */
    public function find(string $credential): ?Passkey
    {
        $options = Session::pull(self::AUTHENTICATE);

        if (blank($options)) {
            return null;
        }

        try {
            return app(FindPasskeyToAuthenticateAction::class)->execute($credential, $options);
        } catch (Throwable) {
            return null;
        }
    }
}
