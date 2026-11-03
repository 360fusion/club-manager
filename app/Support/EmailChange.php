<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Changing the sign-in email. Where mail really goes out, the new address is only held (pending_email) and mailed a link;
 * the account keeps its old, working address until the link is opened, so a typo cannot lock anyone out. Where mail is
 * only logged nobody could open the link, so the address changes at once, as it always did there.
 */
final class EmailChange
{
    /** Holds or applies the new address on the user without saving; returns true when it is held for confirmation. */
    public static function request(User $user, string $email): bool
    {
        if (! EmailVerification::required()) {
            $user->email = $email;

            return false;
        }

        $user->pending_email = $email;

        return true;
    }

    /** Mails the confirmation link; call after the user is saved. */
    public static function sendLink(User $user): void
    {
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addHours(24), ['id' => $user->id, 'hash' => sha1($user->pending_email)]);

        Notification::route('mail', $user->pending_email)->notify(new ConfirmNewEmail($url));
    }
}
