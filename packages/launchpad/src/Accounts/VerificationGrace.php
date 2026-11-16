<?php

namespace Launchpad\Accounts;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Launchpad\Launchpad;

/**
 * The grace period of an account that was already there, unverified, when verification became required: it keeps working, with a
 * banner counting down, until `users.verify_by`. A new sign-up has no deadline, so it is stopped at once; a deadline is cleared when
 * the address is verified, so a later change of email never opens a new grace period.
 */
final class VerificationGrace
{
    /** Whether this account is still inside its grace period */
    public static function covers(object $user): bool
    {
        return $user->verify_by !== null && $user->verify_by->isFuture();
    }

    public static function deadline(): Carbon
    {
        return now()->addDays((int) config('launchpad.verification.grace_days', 14));
    }

    /** Gives every unverified account that has no deadline one, and says how many it was; the team is never gated */
    public static function stampWhereMissing(): int
    {
        return self::unverifiedCustomers()->whereNull('verify_by')->update(['verify_by' => self::deadline()]);
    }

    private static function unverifiedCustomers(): Builder
    {
        return DB::table((new (Launchpad::userModel()))->getTable())
            ->whereNull('email_verified_at')
            ->whereNull('staff_role');
    }
}
