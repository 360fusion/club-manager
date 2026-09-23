<?php

namespace App\Domains\ClubAccounting\Enums;

use Carbon\CarbonInterface;

/**
 * Where a member stands with the online portal. It is worked out from the member's user and club_user row,
 * never stored, so it cannot drift from what the account really is.
 */
enum MemberAccountStatus: string
{
    case NotInvited = 'not_invited';
    case Invited = 'invited';
    case InviteExpired = 'invite_expired';
    case AwaitingApproval = 'awaiting_approval';
    case HasAccount = 'has_account';
    case Deactivated = 'deactivated';

    /**
     * @param  string|null  $pivotStatus  club_user.status, or null when the member has no linked user in this club
     */
    public static function resolve(
        ?string $pivotStatus,
        bool $hasToken,
        ?CarbonInterface $invitedAt,
        ?CarbonInterface $acceptedAt,
        bool $emailVerified,
        int $expiryDays,
    ): self {
        $outstanding = fn () => $invitedAt !== null && $invitedAt->copy()->addDays($expiryDays)->isPast() ? self::InviteExpired : self::Invited;

        return match ($pivotStatus) {
            null => self::NotInvited,
            // Someone already active at the club (e.g. imported, or staff) can still hold an invitation to set a login.
            'active' => match (true) {
                $acceptedAt !== null || $emailVerified => self::HasAccount,
                $hasToken => $outstanding(),
                default => self::NotInvited,
            },
            'pending' => $hasToken ? $outstanding() : self::AwaitingApproval,
            default => self::Deactivated,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotInvited => 'Not invited',
            self::Invited => 'Invited',
            self::InviteExpired => 'Invite expired',
            self::AwaitingApproval => 'Awaiting approval',
            self::HasAccount => 'Has account',
            self::Deactivated => 'Deactivated',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotInvited => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700',
            self::Invited, self::AwaitingApproval => 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::InviteExpired => 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60',
            self::HasAccount => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60',
            self::Deactivated => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
        };
    }

    /** An invitation is outstanding and can be resent or revoked. */
    public function isInvitePending(): bool
    {
        return in_array($this, [self::Invited, self::InviteExpired]);
    }

    public function canInvite(): bool
    {
        return in_array($this, [self::NotInvited, self::Deactivated]);
    }
}
