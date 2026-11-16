<?php

namespace Launchpad\Accounts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Launchpad\Launchpad;
use Launchpad\Notifications\LoginLinkNotification;

/**
 * Sign-in links by email: a signed link that lasts 15 minutes and works once. The token is kept only as a hash in the cache; the link opens
 * a page with a button, so a mail scanner that follows links does not use it up.
 */
final class LoginLinks
{
    public const MINUTES = 15;

    public static function enabled(): bool
    {
        return (bool) config('launchpad.modules.login_links', true);
    }

    /** Sends a link if the address has an open account; says nothing either way */
    public function send(string $email): void
    {
        $user = AccountRegistration::findByEmail($email);

        if ($user === null || $user->isClosed() || $user->isSuspended()) {
            return;
        }

        $token = Str::random(48);
        Cache::put($this->key($token), $user->getKey(), now()->addMinutes(self::MINUTES));
        $user->notify(new LoginLinkNotification(URL::temporarySignedRoute('login.link.show', now()->addMinutes(self::MINUTES), ['token' => $token])));
    }

    /** The account the link was made for, once: a second use, or a link past its time, gives null */
    public function consume(string $token): ?object
    {
        $id = Cache::pull($this->key($token));

        return $id === null ? null : Launchpad::userModel()::find($id);
    }

    private function key(string $token): string
    {
        return 'launchpad.login-link.'.hash('sha256', $token);
    }
}
