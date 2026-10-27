<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Http\Requests\UserAdmin\UpdateCommitteeRoleRequest;
use App\Http\Requests\UserAdmin\UpdateRankRequest;
use App\Http\Requests\UserAdmin\UpdateRoleRequest;
use App\Http\Requests\UserAdmin\UpdateStatus3Request;
use App\Models\Club;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;

/**
 * Changing a member's role, rank, committee role and status, and removing a member from the roster. Split out of UserAdminController; the URLs, route names and permissions are unchanged.
 */
class MemberStatusAdminController extends Controller
{
    /**
     * Update a member's role in the club.
     */
    public function updateRole(UpdateRoleRequest $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $targetRole = $club->users()->where('users.id', $userId)->firstOrFail()->pivot->role;
        abort_unless(ClubAccess::canAssignRole($request->user(), $club, $validated['role'], $targetRole), 403, 'Only a club owner can grant or change the owner role.');

        $club->users()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return redirect()->back()->with('success', 'Member role updated to '.strtoupper($validated['role']));
    }

    /**
     * Update a member's rank in the club.
     */
    public function updateRank(UpdateRankRequest $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $club->users()->where('users.id', $userId)->firstOrFail();

        $validated = $request->validated();

        $club->users()->updateExistingPivot($userId, ['rank' => $validated['rank']]);

        return redirect()->back()->with('success', 'Member rank updated.');
    }

    /**
     * Update a member's committee role in the club.
     */
    public function updateCommitteeRole(UpdateCommitteeRoleRequest $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $club->users()->where('users.id', $userId)->firstOrFail();

        $validated = $request->validated();

        $roleValue = ! empty($validated['committee_role']) ? $validated['committee_role'] : null;

        $club->users()->updateExistingPivot($userId, ['committee_role' => $roleValue]);

        $roleLabel = match ($roleValue) {
            'chair' => 'Committee Chair',
            'secretary' => 'Committee Secretary',
            'member' => 'Committee Member',
            default => 'None',
        };

        return redirect()->back()->with('success', "Committee role updated to {$roleLabel}.");
    }

    /**
     * Update member status (active, inactive, past, pending, deleted).
     */
    public function updateStatus(UpdateStatus3Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $this->guardOwner($club, $userId);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $validated = $request->validated();

        $club->users()->updateExistingPivot($userId, ['status' => $validated['status']]);

        $messages = [
            'active' => "{$user->name} has been restored and reactivated on the active roster.",
            'inactive' => "{$user->name} status set to Deactivated (access paused).",
            'past' => "{$user->name} moved to Past Members list.",
            'pending' => "{$user->name} status set to pending.",
            'deleted' => "{$user->name} has been deleted from the member directory.",
        ];

        return redirect()->back()->with('success', $messages[$validated['status']] ?? 'Member status updated.');
    }

    /**
     * Remove a member from active roster to Past Members.
     */
    public function removeMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $this->guardOwner($club, $userId);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $club->users()->updateExistingPivot($userId, ['status' => ClubMemberStatus::Past->value]);

        return redirect()->back()->with('success', "{$user->name} moved to Past Members list.");
    }

    /**
     * Delete a member from the admin directory (preserves historical database records).
     */
    public function forceDeleteMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $this->guardOwner($club, $userId);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        // Soft hide member by setting pivot status to 'deleted'
        $club->users()->updateExistingPivot($userId, ['status' => ClubMemberStatus::Deleted->value]);

        return redirect()->route('admin.users.index', ['clubSlug' => $clubSlug])
            ->with('success', "{$user->name} has been deleted from the member directory. Historical records remain preserved.");
    }

    /**
     * Only an owner (or a platform super admin) may suspend or remove a club owner.
     */
    private function guardOwner(Club $club, int $userId): void
    {
        $actor = request()->user();
        $targetRole = $club->users()->where('users.id', $userId)->first()?->pivot->role;

        abort_if(
            $targetRole === 'owner' && ! $actor->is_super_admin && ClubAccess::role($actor, $club) !== 'owner',
            403,
            'Only a club owner can change or remove another owner.',
        );
    }
}
