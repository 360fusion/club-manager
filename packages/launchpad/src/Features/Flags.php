<?php

namespace Launchpad\Features;

use Laravel\Pennant\Feature;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\Setting;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\CurrentWorkspace;

/**
 * The flags listed in config launchpad.features, given to Laravel Pennant with a workspace as their scope (so `Feature::active('x')`, `@feature('x')` and
 * the `feature:x` middleware all ask about the current workspace; with launchpad.features_default_scope off, ask with `Feature::for($workspace)`). A flag is worked out the first time a workspace is asked about it, and Pennant keeps
 * the answer: after changing a flag's rules in config, `php artisan pennant:purge x` makes every workspace be worked out again.
 * The first of: what staff set for everyone (Setting feature_<key>: 'on' or 'off'), the flag's `on`, its `plans`, then its `rollout` (a percentage; the same
 * workspaces every time). What staff set for one workspace is Pennant's own stored value for it.
 */
final class Flags
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return (array) config('launchpad.features');
    }

    public static function register(): void
    {
        if (self::all() === []) {
            return;
        }

        // the current team is the default scope, unless the app keeps its own (launchpad.features_default_scope)
        if (config('launchpad.features_default_scope', true)) {
            Feature::resolveScopeUsing(fn () => app(CurrentWorkspace::class)->workspace);
        }

        foreach (self::all() as $key => $flag) {
            // mixed: with the default scope left to the app, Pennant may hand over a user; that answers as no team
            Feature::define($key, fn (mixed $scope) => self::resolve($key, $flag, $scope instanceof Workspace ? $scope : null));
        }
    }

    /** A workspace's plan changed: the flags that depend on it are worked out afresh (which also drops what staff set for it on those flags) */
    public static function planChanged(Workspace $workspace): void
    {
        $keys = array_keys(array_filter(self::all(), fn ($flag) => ! empty($flag['plans'])));

        $keys && Feature::for($workspace)->forget($keys);
    }

    private static function resolve(string $key, array $flag, ?Workspace $workspace): bool
    {
        $global = Setting::get("feature_{$key}");

        if (in_array($global, ['on', 'off'], true)) {
            return $global === 'on';
        }

        if (! empty($flag['on'])) {
            return true;
        }

        if ($workspace === null) {
            return false;
        }

        if (in_array(app(Entitlements::class)->plan($workspace)->key, (array) ($flag['plans'] ?? []), true)) {
            return true;
        }

        return crc32($key.'|'.$workspace->getKey()) % 100 < (int) ($flag['rollout'] ?? 0);
    }
}
