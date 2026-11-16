<?php

namespace Launchpad\Admin\Concerns;

use Filament\Panel;

/**
 * Add to the User model (beside HasLaunchpadAccount) and implement Filament's `FilamentUser`, `HasAppAuthentication` and
 * `HasAppAuthenticationRecovery` on it. The admin lets in staff only, and asks them for a code from an authenticator app.
 */
trait HasAdminAccess
{
    public function initializeHasAdminAccess(): void
    {
        $this->casts['app_authentication_secret'] = 'encrypted';
        $this->casts['app_authentication_recovery_codes'] = 'encrypted:array';
        $this->makeHidden(['app_authentication_secret', 'app_authentication_recovery_codes']);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && ! $this->isClosed();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<int, string>|null the hashed codes */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  array<int, string>|null  $codes  the hashed codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
