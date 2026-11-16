<?php

namespace Launchpad\Admin;

use Illuminate\Database\Eloquent\Model;

/** What staff may not do to an account, however they got to the button */
final class UserSafeguards
{
    public static function isSelf(Model $record): bool
    {
        return AdminLog::actor()?->is($record) ?? false;
    }

    /** Closing needs an account that is open and is not the one staff are signed in with */
    public static function canClose(Model $record): bool
    {
        return ! $record->isClosed() && ! self::isSelf($record) && StaffAccess::allowed('close-accounts');
    }

    /** Suspending needs an open customer account (not staff, not oneself) and the close-accounts permission */
    public static function canSuspend(Model $record): bool
    {
        return ! $record->isClosed() && ! $record->isSuspended() && ! $record->isStaff() && ! self::isSelf($record) && StaffAccess::allowed('close-accounts');
    }

    /** Only someone who may manage staff hands out staff roles, and nobody changes their own */
    public static function canChangeStaffRole(Model $record): bool
    {
        return StaffAccess::allowed('manage-staff') && ! self::isSelf($record);
    }
}
