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

        if (! preg_match('#^[^/]+/admin(?:/([^/?]+)(?:/(.*))?)?#', $path, $matches)) {
            return null;
        }

        $segment = $matches[1] ?? '';
        $rest = $matches[2] ?? '';

        if ($segment === 'accounting' && self::isReadOnlyAccountingPath($rest)) {
            return 'view_accounting';
        }

        return config('club_permissions.route_map')[$segment] ?? null;
    }

    /**
     * Some /admin/{club}/accounting/... paths are read-only despite living
     * alongside billing-mutating ones under the same 'accounting' route_map
     * entry, so an examiner (who only ever holds view_accounting, never
     * manage_billing) can reach them. route_map can't express this — it only
     * ever sees the first path segment ('accounting') — so it is resolved
     * here instead, narrowly, by exact known path shape.
     *
     * 'contacts' is deliberately left out of the tab list below even though
     * it is a read-only SPA tab: its path is byte-identical to the POST
     * .../accounting/contacts route (create a contact), which has no capability
     * check of its own beyond this one — including it here would let an
     * examiner create contacts. Every path this returns false for keeps
     * requiring manage_billing, unchanged.
     */
    private static function isReadOnlyAccountingPath(string $rest): bool
    {
        if ($rest === '') {
            return true; // bare /admin/{club}/accounting
        }

        // AccountingAdminController::index()'s own SPA tabs; 'reporting' optionally
        // carries a /{report} suffix.
        $readOnlyTabs = ['home', 'sales', 'purchases', 'accounting', 'bank-accounts', 'chart-of-accounts', 'reconciliation', 'settings'];

        if (in_array($rest, $readOnlyTabs, true) || preg_match('#^reporting(/[^/]+)?$#', $rest)) {
            return true;
        }

        $readOnlyPatterns = [
            '#^invoices/\d+/edit$#',
            '#^bills/\d+/edit$#',
            '#^attachments/\d+$#',
            '#^reports/[^/]+/export(-pdf)?$#',
            '#^activity/[^/]+/\d+$#',
            '#^treasurer-report/export-(pdf|csv)$#',
            '#^vat-return/export$#',
            '#^independent-examiner-report/export-pdf$#',
        ];

        foreach ($readOnlyPatterns as $pattern) {
            if (preg_match($pattern, $rest)) {
                return true;
            }
        }

        return false;
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
