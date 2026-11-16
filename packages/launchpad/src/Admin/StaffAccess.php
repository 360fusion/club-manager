<?php

namespace Launchpad\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * What a member of staff may do in the admin. A permission (config launchpad.admin.permissions) is had by the staff roles listed for it, unless an
 * admin ticked or unticked it for that person (users.staff_permissions). A customer, who has no staff role, has none.
 */
final class StaffAccess
{
    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function permissions(): array
    {
        return (array) config('launchpad.admin.permissions');
    }

    /** Whether this person has it, by their role or by what was ticked for them */
    public static function can(?Model $user, string $key): bool
    {
        $role = $user?->staff_role;

        if ($role === null) {
            return false;
        }

        $ticked = (array) ($user->staff_permissions ?? []);

        return array_key_exists($key, $ticked) ? (bool) $ticked[$key] : in_array($role, (array) (self::permissions()[$key][2] ?? []), true);
    }

    /** Whether the member of staff who is acting now has it */
    public static function allowed(string $key): bool
    {
        return self::can(AdminLog::actor(), $key);
    }

    /** @return list<string> the keys this person has */
    public static function keysOf(Model $user): array
    {
        return array_values(array_filter(array_keys(self::permissions()), fn ($key) => self::can($user, $key)));
    }

    /**
     * Saves a role and the ticked keys for a person: only what differs from the role's own gives is kept, so a role's later gives still reach them.
     *
     * @param  list<string>  $keys
     */
    public static function save(Model $user, string $role, array $keys): void
    {
        $overrides = [];

        foreach (self::permissions() as $key => [, , $roles]) {
            $has = in_array($key, $keys, true);

            if ($has !== in_array($role, $roles, true)) {
                $overrides[$key] = $has;
            }
        }

        $user->forceFill(['staff_role' => $role, 'staff_permissions' => $overrides ?: null])->save();
    }

    /** Whether somebody else (open, staff) would still be able to manage staff if this person were not */
    public static function someoneElseManagesStaff(Model $user): bool
    {
        return $user::query()->whereNotNull('staff_role')->whereNull('deletion_pending_at')->whereKeyNot($user->getKey())->get()
            ->contains(fn ($other) => self::can($other, 'manage-staff'));
    }
}
