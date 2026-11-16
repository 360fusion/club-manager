<?php

namespace Launchpad\Accounts\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Launchpad\Models\SocialAccount;
use Launchpad\Support\Locales;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

/**
 * What Launchpad needs from the model that signs in. Use it on App\Models\User (which also implements MustVerifyEmail).
 */
trait HasLaunchpadAccount
{
    // the passkeys relation and names; passkeys are on once the User also implements HasPasskeys
    use InteractsWithPasskeys;

    public function initializeHasLaunchpadAccount(): void
    {
        $this->casts['verify_by'] = 'datetime';
        $this->casts['terms_accepted_at'] = 'datetime';
        $this->casts['password_set_at'] = 'datetime';
        // encrypted at rest, so a copy of the database alone does not hand out a second factor
        $this->casts['two_factor_secret'] = 'encrypted';
        $this->casts['two_factor_recovery_codes'] = 'encrypted:array';
        $this->casts['two_factor_confirmed_at'] = 'datetime';
        $this->casts['deletion_pending_at'] = 'datetime';
        $this->casts['notification_choices'] = 'array';
        $this->casts['staff_permissions'] = 'array';
        $this->makeHidden(['two_factor_secret', 'two_factor_recovery_codes']);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'user_id');
    }

    /** One of the team ('admin' or 'support'), not a customer; a team member is never held back by the verification gate */
    public function isStaff(): bool
    {
        return $this->staff_role !== null;
    }

    /** The language this person chose, if it is still one the site offers (also what notifications and mail are sent in) */
    public function preferredLocale(): ?string
    {
        return isset(Locales::supported()[$this->locale]) ? $this->locale : null;
    }

    /** Whether the account has been closed and waits to be removed (it can be restored until then) */
    public function isClosed(): bool
    {
        return $this->deletion_pending_at !== null;
    }

    /** Whether staff have suspended the account for now (Launchpad\Accounts\Suspension) */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
