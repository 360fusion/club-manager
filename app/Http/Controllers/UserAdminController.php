<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ClubAccess;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    /**
     * Display member roster for a club in the admin portal.
     */
    public function index(string $clubSlug, MemberInvitationService $invitations): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $members = $club->users()
            ->wherePivot('status', '!=', 'deleted')
            ->get()
            ->map(function ($u) use ($club, $invitations) {
                $isJoinRequest = $u->pivot->status === 'pending' && empty($u->pivot->invitation_token);

                return [
                    'matching_member' => $isJoinRequest ? $invitations->matchForUser($club, $u)?->full_name : null,
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->pivot->role ?? 'member',
                    'rank' => $u->pivot->rank ?? '',
                    'committee_role' => $u->pivot->committee_role ?? null,
                    'member_number' => $u->pivot->member_number ?? ('MEM-'.$u->id),
                    'status' => $u->pivot->status ?? 'active',
                    'invitation_token' => ! empty($u->pivot->invitation_token),
                    'invited_at' => $u->pivot->invited_at ? Carbon::parse($u->pivot->invited_at)->format('M d, Y') : null,
                    'invitation_accepted_at' => $u->pivot->invitation_accepted_at ? Carbon::parse($u->pivot->invitation_accepted_at)->format('M d, Y') : null,
                    'joined_at' => $u->pivot->created_at ? Carbon::parse($u->pivot->created_at)->format('M d, Y') : 'Recent',
                ];
            });

        $enableMemberRanks = $club->settings['enable_member_ranks'] ?? true;
        $defaultRanks = [
            'Worshipful Master',
            'Senior Warden',
            'Junior Warden',
            'Chaplain',
            'Treasurer',
            'Secretary',
            'Director of Ceremonies',
            'Almoner',
            'Charity Steward',
            'Membership Officer',
            'Mentor',
            'Senior Deacon',
            'Junior Deacon',
            'Asst Dir of Ceremonies',
            'Organist',
            'Assistant Secretary',
            'Inner Guard',
            'Stewards',
            'Tyler',
            'Immediate Past Master',
        ];
        $memberRanks = $club->settings['member_ranks'] ?? $defaultRanks;

        return Inertia::render('Admin/Users/Index', [
            'club' => $club,
            'members' => $members,
            'enableMemberRanks' => $enableMemberRanks,
            'memberRanks' => $memberRanks,
        ]);
    }

    /**
     * Display detailed profile page for a member in the admin portal.
     */
    public function show(string $clubSlug, int $userId): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $memberPivot = $user->pivot;

        $invoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->invoice_number,
                'amount_formatted' => Currencies::format($i->amount, $club),
                'status' => $i->status,
                'due_date' => $i->due_date?->format('M d, Y') ?? 'Immediate',
            ]);

        $rsvps = EventRegistration::with(['event', 'attendees'])
            ->where('user_id', $userId)
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->get()
            ->sortByDesc(fn ($r) => $r->event->starts_at)
            ->values()
            ->map(fn (EventRegistration $r) => [
                'event_title' => $r->event->title,
                'event_date' => $r->event->starts_at ? $r->event->starts_at->format('M d, Y @ H:i') : 'TBD',
                'location' => $r->event->location,
                'rsvp_status' => $r->status ?? 'attending',
                'attended' => $r->booker()?->checked_in_at !== null,
            ]);

        $totalRsvps = $rsvps->count();
        $attendedCount = $rsvps->where('attended', true)->count();
        $attendanceRate = $totalRsvps > 0 ? round(($attendedCount / $totalRsvps) * 100) : 100;

        $userClubs = $user->clubs->map(fn ($c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'role' => $c->pivot->role,
        ]);

        $memberData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $memberPivot->role ?? 'member',
            'rank' => $memberPivot->rank ?? '',
            'member_number' => $memberPivot->member_number ?? ('MEM-'.$user->id),
            'status' => $memberPivot->status ?? 'active',
            'phone' => $memberPivot->phone ?? '',
            'emergency_contact' => $memberPivot->emergency_contact ?? '',
            'dietary_notes' => $memberPivot->dietary_notes ?? '',
            'invitation_token' => ! empty($memberPivot->invitation_token),
            'invited_at' => $memberPivot->invited_at ? Carbon::parse($memberPivot->invited_at)->format('M d, Y') : null,
            'invitation_accepted_at' => $memberPivot->invitation_accepted_at ? Carbon::parse($memberPivot->invitation_accepted_at)->format('M d, Y') : null,
            'joined_at' => $memberPivot->created_at ? Carbon::parse($memberPivot->created_at)->format('M d, Y') : 'Recent',
            'two_factor_enabled' => ! empty($user->two_factor_secret),
        ];

        $enableMemberRanks = $club->settings['enable_member_ranks'] ?? true;
        $memberRanks = $club->settings['member_ranks'] ?? ['Novice', 'Intermediate', 'Senior', 'Captain', 'Coxswain', 'Veteran'];

        return Inertia::render('Admin/Users/Show', [
            'club' => $club,
            'member' => $memberData,
            'userClubs' => $userClubs,
            'rsvps' => $rsvps,
            'invoices' => $invoices,
            'enableMemberRanks' => $enableMemberRanks,
            'memberRanks' => $memberRanks,
            'stats' => [
                'total_rsvps' => $totalRsvps,
                'attended_count' => $attendedCount,
                'attendance_rate' => $attendanceRate,
                'total_invoices_paid' => $invoices->where('status', 'paid')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created member in the club roster.
     */
    public function storeMember(Request $request, string $clubSlug, MemberInvitationService $invitations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|in:owner,admin,coach,member,treasurer',
            'rank' => 'nullable|string|max:100',
            'committee_role' => 'nullable|in:chair,secretary,member',
            'member_number' => 'nullable|string|max:100',
            'send_invite' => 'nullable|boolean',
        ]);

        abort_unless(ClubAccess::canAssignRole($request->user(), $club, $validated['role'], null), 403, 'Only a club owner can grant the owner role.');

        $email = strtolower($validated['email']);

        if ($club->users()->whereRaw('lower(users.email) = ?', [$email])->exists()) {
            return redirect()->back()->with('error', 'User is already a member of this club.');
        }

        $sendInvite = $request->boolean('send_invite', true);
        $pivotDetails = [
            'role' => $validated['role'],
            'rank' => $validated['rank'] ?? null,
            'committee_role' => $validated['committee_role'] ?? null,
            'member_number' => ($validated['member_number'] ?? null) ?: ('MEM-'.random_int(1000, 9999)),
        ];

        try {
            $sent = DB::transaction(function () use ($club, $validated, $email, $sendInvite, $pivotDetails, $request, $invitations) {
                $nameParts = explode(' ', trim($validated['name']), 2);

                $member = Member::where('club_id', $club->id)->whereNull('user_id')->whereRaw('lower(email) = ?', [$email])->first()
                    ?? Member::create([
                        'club_id' => $club->id,
                        'email' => $email,
                        'first_name' => $nameParts[0],
                        'last_name' => $nameParts[1] ?? '',
                        'masonic_rank' => $validated['rank'] ?? 'Bro',
                        'membership_status' => MembershipStatus::Active,
                        'current_office' => LodgeOffice::Member,
                    ]);

                if ($sendInvite) {
                    $sent = $invitations->invite($member, $club, $request->user());
                    $club->users()->updateExistingPivot($member->fresh()->user_id, $pivotDetails);

                    return $sent;
                }

                $user = User::firstOrCreate(['email' => $email], ['name' => $validated['name'], 'password' => Hash::make(Str::random(16))]);
                $club->users()->attach($user->id, $pivotDetails + ['status' => 'active']);
                $member->forceFill(['user_id' => $user->id])->save();

                return null;
            });
        } catch (MemberInvitationException $e) {
            return redirect()->back()->with('error', $e->getMessage().'.');
        }

        if ($sent === null) {
            return redirect()->back()->with('success', 'Member added successfully to roster.');
        }

        return redirect()->back()->with('success', $sent['emailed']
            ? "Member added to roster & invitation email sent to {$email}."
            : "Member added to roster. Invitation link: {$sent['url']}");
    }

    /**
     * Send or resend an email invitation to a member.
     */
    public function sendInvite(Request $request, string $clubSlug, int $userId, MemberInvitationService $invitations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = $club->users()->where('users.id', $userId)->first();

        if (! $user) {
            return redirect()->back()->with('error', 'User is not a member of this club.');
        }

        try {
            $member = $invitations->memberForUser($club, $user);
            $sent = $member->accountStatus($club)->isInvitePending()
                ? $invitations->resend($member, $club, $request->user())
                : $invitations->invite($member, $club, $request->user());
        } catch (MemberInvitationException $e) {
            return redirect()->back()->with('error', $e->getMessage().'.');
        }

        return redirect()->back()->with('success', $sent['emailed']
            ? "Invitation email sent successfully to {$user->email}."
            : "Invitation token created. Share activation link: {$sent['url']}");
    }

    /**
     * Update a member's role in the club.
     */
    public function updateRole(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'role' => 'required|in:owner,admin,coach,member,treasurer',
        ]);

        $targetRole = $club->users()->where('users.id', $userId)->first()?->pivot->role;
        abort_unless(ClubAccess::canAssignRole($request->user(), $club, $validated['role'], $targetRole), 403, 'Only a club owner can grant or change the owner role.');

        $club->users()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return redirect()->back()->with('success', 'Member role updated to '.strtoupper($validated['role']));
    }

    /**
     * Update a member's rank in the club.
     */
    public function updateRank(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'rank' => 'nullable|string|max:100',
        ]);

        $club->users()->updateExistingPivot($userId, ['rank' => $validated['rank']]);

        return redirect()->back()->with('success', 'Member rank updated.');
    }

    /**
     * Update a member's committee role in the club.
     */
    public function updateCommitteeRole(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'committee_role' => 'nullable|in:chair,secretary,member',
        ]);

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
     * Revoke an active invitation for a member.
     */
    public function revokeInvite(string $clubSlug, int $userId, MemberInvitationService $invitations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $invitations->revoke($club, $user);

        return redirect()->back()->with('success', "Invitation revoked for {$user->name}.");
    }

    /**
     * Update member status (active, inactive, past, pending, deleted).
     */
    public function updateStatus(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $this->guardOwner($club, $userId);
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,past,pending,deleted',
        ]);

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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $this->guardOwner($club, $userId);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $club->users()->updateExistingPivot($userId, ['status' => 'past']);

        return redirect()->back()->with('success', "{$user->name} moved to Past Members list.");
    }

    /**
     * Delete a member from the admin directory (preserves historical database records).
     */
    public function forceDeleteMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $this->guardOwner($club, $userId);
        $user = User::findOrFail($userId);

        // Soft hide member by setting pivot status to 'deleted'
        $club->users()->updateExistingPivot($userId, ['status' => 'deleted']);

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
