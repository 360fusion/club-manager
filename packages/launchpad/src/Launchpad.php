<?php

namespace Launchpad;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class Launchpad
{
    private static bool $runsMigrations = true;

    /**
     * For an app that already has some of these tables (users' two-factor columns, notifications, subscriptions...): Launchpad's migrations
     * are not run. Call it in a service provider's register(), then publish the ones you want (php artisan vendor:publish
     * --tag=launchpad-migrations) and adapt them. As Sanctum and Cashier do.
     */
    public static function ignoreMigrations(): void
    {
        self::$runsMigrations = false;
    }

    public static function runsMigrations(): bool
    {
        return self::$runsMigrations;
    }

    /** @return class-string<Model&Authenticatable> */
    public static function userModel(): string
    {
        return config('launchpad.user_model');
    }
}
