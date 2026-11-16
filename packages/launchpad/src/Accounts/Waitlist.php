<?php

namespace Launchpad\Accounts;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Launchpad\Models\Invitation;
use Launchpad\Models\WaitlistEntry;
use Launchpad\Notifications\WaitlistInvitationNotification;

/**
 * While modules.waitlist is on, sign-up is closed: people join the waitlist at /waitlist, and a new account can be made only for an address
 * staff have let in, or one with an open invitation to a team. It applies to the sign-up form, buying first and Google. Single sign-on is
 * not held back: its team is already in, and its people are the team's to add.
 */
final class Waitlist
{
    public static function enabled(): bool
    {
        return (bool) config('launchpad.modules.waitlist');
    }

    public static function allows(string $email): bool
    {
        $email = AccountRegistration::normalizeEmail($email);

        return ! self::enabled()
            || WaitlistEntry::where('email', $email)->whereNotNull('invited_at')->exists()
            || Invitation::active()->where('email', $email)->exists();
    }

    public static function message(): string
    {
        return __('Sign-up is by invitation for now. Join the waitlist and we will email you when there is room.');
    }

    /** Joins, or does nothing for an address already on it */
    public function join(string $email, ?string $name): void
    {
        WaitlistEntry::firstOrCreate(['email' => AccountRegistration::normalizeEmail($email)], ['name' => $name]);
    }

    public function invite(WaitlistEntry $entry): void
    {
        $entry->forceFill(['invited_at' => now()])->save();
        $url = URL::route('register', ['email' => $entry->email]);
        Notification::route('mail', $entry->email)->notify(new WaitlistInvitationNotification($url, (string) $entry->name));
    }
}
