<?php

namespace Launchpad\Accounts;

/** Which optional emails a person has chosen to get (config launchpad.accounts.notifications). An app asks before it sends one. */
final class NotificationPreferences
{
    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function all(): array
    {
        return (array) config('launchpad.accounts.notifications');
    }

    public static function wants(object $user, string $key): bool
    {
        $default = (bool) (self::all()[$key][2] ?? false);
        $chosen = (array) ($user->notification_choices ?? []);

        return array_key_exists($key, $chosen) ? (bool) $chosen[$key] : $default;
    }
}
