<?php

namespace Launchpad\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Launchpad\Models\AccountEvent;

/**
 * Staff signing in as a customer to see what they see. The staff member stays signed in to the admin on its own guard; the customer is signed
 * in on the web guard for as long as the session note below lives. It is the customer's session, so what the customer cannot do, nor can staff:
 * and the things in config launchpad.impersonation.blocked (changing the password, two-factor, closing the account ...) are refused as well.
 * Starting and stopping are in the admin log, and the customer's own Activity page says that support signed in.
 */
final class Impersonation
{
    private const KEY = 'launchpad.impersonating';

    /** @return array{staff_id: int|string, started_at: int}|null */
    public static function current(): ?array
    {
        return session()->get(self::KEY);
    }

    public static function active(): bool
    {
        return self::current() !== null;
    }

    public static function staff(): ?Model
    {
        $note = self::current();

        return $note ? (Auth::guard('admin')->user()?->getKey() === $note['staff_id'] ? Auth::guard('admin')->user() : null) : null;
    }

    /** Whether this staff member may sign in as this person: needs the permission, and nobody signs in as staff, a closed account or themselves */
    public static function allowed(?Model $staff, Model $target): bool
    {
        return $staff !== null
            && StaffAccess::can($staff, 'impersonate')
            && $staff->getKey() !== $target->getKey()
            && ! (method_exists($target, 'isStaff') && $target->isStaff())
            && ! (method_exists($target, 'isClosed') && $target->isClosed());
    }

    public static function start(Model $staff, Model $target): void
    {
        // the note goes first: the sign-in below is not a sign-in the customer made, and the Login listener leaves it off their Activity page
        session()->put(self::KEY, ['staff_id' => $staff->getKey(), 'started_at' => now()->timestamp]);
        Auth::guard('web')->login($target);

        AdminLog::record('user.impersonate_started', $target, "Signed in as {$target->email}", actor: $staff);
        AccountEvent::record($target, 'support_signed_in');
    }

    public static function stop(): ?Model
    {
        $staff = self::staff();
        $target = Auth::guard('web')->user();

        Auth::guard('web')->logout();
        session()->forget(self::KEY);

        if ($target) {
            AdminLog::record('user.impersonate_stopped', $target, "Stopped signing in as {$target->email}", actor: $staff);
        }

        return $staff;
    }
}
