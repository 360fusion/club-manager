<?php

namespace App\Support;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
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

        return $membership && $membership->pivot->status === ClubMemberStatus::Active->value ? $membership->pivot->role : null;
    }

    public static function isActiveMember(?User $user, Club $club): bool
    {
        return $user !== null && ($user->actsAsSuperAdmin() || self::role($user, $club) !== null);
    }

    /**
     * Whether the user holds a capability (see config/club_permissions.php) at the club.
     */
    public static function can(?User $user, Club $club, string $capability): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->actsAsSuperAdmin() || ClubPermissions::allows($club, self::role($user, $club), $capability);
    }

    public static function authorize(?User $user, Club $club, string $capability): void
    {
        abort_unless(self::can($user, $club, $capability), 403, 'Your role does not have access to this area of the club.');
    }

    /**
     * Whether the actor may set a member to $newRole, given the member's current role.
     * Only an owner may grant, change or remove the owner role, and the last owner stays.
     * Only an owner/admin/treasurer may grant or revoke the examiner role, since it
     * grants read access to the club's full accounting records. Only an owner or admin
     * may grant the admin role or change an admin's role, and nobody below admin may
     * change their own role (pass $targetUserId), so a Secretary cannot promote themselves.
     */
    public static function canAssignRole(User $actor, Club $club, string $newRole, ?string $targetCurrentRole, ?int $targetUserId = null): bool
    {
        if ($actor->actsAsSuperAdmin()) {
            return true;
        }

        $actorRole = self::role($actor, $club);

        if ($actorRole === ClubRole::Owner->value) {
            $owners = $club->users()->wherePivot('status', ClubMemberStatus::Active->value)->wherePivot('role', ClubRole::Owner->value)->count();

            return ! ($targetCurrentRole === ClubRole::Owner->value && $newRole !== ClubRole::Owner->value && $owners <= 1);
        }

        if (($newRole === ClubRole::Examiner->value || $targetCurrentRole === ClubRole::Examiner->value) && ! in_array($actorRole, [ClubRole::Admin->value, ClubRole::Treasurer->value], true)) {
            return false;
        }

        if ($actorRole !== ClubRole::Admin->value) {
            $touchesAdmin = in_array(ClubRole::Admin->value, [$newRole, $targetCurrentRole], true);

            if ($touchesAdmin || ($targetUserId !== null && $targetUserId === $actor->id)) {
                return false;
            }
        }

        return $actorRole !== null && $newRole !== ClubRole::Owner->value && $targetCurrentRole !== ClubRole::Owner->value;
    }

    /**
     * Whether this person may download a club's whole set of accounting records: the lodge owner, or a platform super admin.
     */
    public static function canExportAllData(?User $user, Club $club): bool
    {
        return $user !== null && ($user->actsAsSuperAdmin() || self::role($user, $club) === ClubRole::Owner->value);
    }
}
