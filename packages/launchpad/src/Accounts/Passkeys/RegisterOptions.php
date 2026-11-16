<?php

namespace Launchpad\Accounts\Passkeys;

use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Webauthn\AuthenticatorSelectionCriteria;

/** The package's options, with the device's own lock (PIN, fingerprint, face) required rather than preferred */
class RegisterOptions extends GeneratePasskeyRegisterOptionsAction
{
    public function authenticatorSelection(): AuthenticatorSelectionCriteria
    {
        return new AuthenticatorSelectionCriteria(
            null,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );
    }
}
