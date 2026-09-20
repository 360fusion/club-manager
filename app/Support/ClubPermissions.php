<?php

namespace App\Support;

use App\Models\Club;

/**
 * Resolves what a club role is allowed to do.
 *
 * Defaults come from config/club_permissions.php. A club may override the
 * roles for any capability via club.settings.permission_matrix, which the
 * club settings screen edits.
 */
class ClubPermissions
{
    /**
     * The effective capability matrix for a club: defaults with the club's
     * own overrides merged over the top.
     *
     * @return array<string, array{label?: string, description?: string, roles: list<string>}>
     */
    public static function matrixFor(Club $club): array
    {
        $defaults = config('club_permissions.capabilities', []);
        $overrides = $club->settings['permission_matrix'] ?? [];

        if (! is_array($overrides)) {
            return $defaults;
        }

        foreach ($overrides as $capability => $override) {
            if (! isset($defaults[$capability]) || ! is_array($override)) {
                continue;
            }

            if (isset($override['roles']) && is_array($override['roles'])) {
                $defaults[$capability]['roles'] = array_values($override['roles']);
            }
        }

        return $defaults;
    }

    /**
     * The capability guarding an admin path, or null if the path is not mapped.
     */
    public static function capabilityForPath(string $path): ?string
    {
        $path = trim($path, '/');

        if (! preg_match('#^clubs/[^/]+/admin(?:/([^/?]+))?#', $path, $matches)) {
            return null;
        }

        $segment = $matches[1] ?? '';

        return config('club_permissions.route_map')[$segment] ?? null;
    }

    /**
     * Whether a pivot role holds a capability at a club.
     */
    public static function allows(Club $club, ?string $role, string $capability): bool
    {
        if ($role === null) {
            return false;
        }

        if (in_array($role, config('club_permissions.always_allowed_roles', []), true)) {
            return true;
        }

        $matrix = self::matrixFor($club);

        if (! isset($matrix[$capability]['roles'])) {
            return false;
        }

        return in_array($role, $matrix[$capability]['roles'], true);
    }
}
