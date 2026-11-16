<?php

namespace Launchpad\Accounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Checking a password in one place. An account brought over from another system may hold a standard Argon2 hash ($argon2id$...), which PHP
 * verifies itself; Laravel's own bcrypt check refuses a hash that is not bcrypt. A hash from elsewhere is replaced with one of the app's own
 * the first time its password is right, so the old kind disappears by itself.
 */
final class PasswordCheck
{
    public static function verify(Model $user, string $plain): bool
    {
        $hash = (string) $user->getAuthPassword();

        if ($hash === '') {
            return false;
        }

        $right = str_starts_with($hash, '$argon2') ? password_verify($plain, $hash) : Hash::check($plain, $hash);

        if ($right && str_starts_with($hash, '$argon2')) {
            $user->forceFill(['password' => Hash::make($plain)])->save();
        }

        return $right;
    }
}
