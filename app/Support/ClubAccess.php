<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;

/**
 * Who may do what at a club. The single place that answers "is this user staff
 * here, and can they do X", so controllers do not each invent their own check.
 */
final class ClubAccess
{
    /**
     * The user's role at the club, or null unless they are an active member.
     */
    public static function role(?User $user, Club $club): ?string
    {
        if ($user === null) {
            return null;
        }

        $membership = $user->clubs()->where('clubs.id', $club->id)->first();

        return $membership && $membership->pivot->status === 'active' ? $membership->pivot->role : null;
    }

    public static function isActiveMember(?User $user, Club $club): bool
    {
        return $user !== null && ($user->is_super_admin || self::role($user, $club) !== null);
    }

    /**
     * Whether the user holds a capability (see config/club_permissions.php) at the club.
     */
    public static function can(?User $user, Club $club, string $capability): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_super_admin || ClubPermissions::allows($club, self::role($user, $club), $capability);
    }

    public static function authorize(?User $user, Club $club, string $capability): void
    {
        abort_unless(self::can($user, $club, $capability), 403, 'Your role does not have access to this area of the club.');
    }

    /**
     * Whether the actor may set a member to $newRole, given the member's current role.
     * Only an owner may grant, change or remove the owner role, and the last owner stays.
     */
    public static function canAssignRole(User $actor, Club $club, string $newRole, ?string $targetCurrentRole): bool
    {
        if ($actor->is_super_admin) {
            return true;
        }

        $actorRole = self::role($actor, $club);

        if ($actorRole === 'owner') {
            $owners = $club->users()->wherePivot('status', 'active')->wherePivot('role', 'owner')->count();

            return ! ($targetCurrentRole === 'owner' && $newRole !== 'owner' && $owners <= 1);
        }

        return $actorRole !== null && $newRole !== 'owner' && $targetCurrentRole !== 'owner';
    }
}
