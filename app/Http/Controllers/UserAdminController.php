<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Enums\ClubMemberStatus;
use App\Enums\DocumentStatus;
use App\Http\Requests\UserAdmin\StoreMemberRequest;
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
    public function index(Request $request, string $clubSlug, MemberInvitationService $invitations): Response
    {
        $club = $this->findClub($clubSlug);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'tab' => (string) $request->query('tab', 'all'),
            'role' => (string) $request->query('role', ''),
            'rank' => (string) $request->query('rank', ''),
            'status' => (string) $request->query('status', ''),
            'committee' => (string) $request->query('committee', ''),
        ];

        $tabStatus = ['active' => 'active', 'invited_pending' => 'pending', 'deactivated' => 'inactive', 'past' => 'past'][$filters['tab']] ?? null;

        $query = $club->users()->wherePivot('status', '!=', 'deleted');
        if ($tabStatus) {
            $query->wherePivot('status', $tabStatus);
        }
        if ($filters['status'] !== '') {
            $query->wherePivot('status', $filters['status']);
        }
        if ($filters['role'] !== '') {
            $query->wherePivot('role', $filters['role']);
        }
        if ($filters['rank'] !== '') {
            $query->wherePivot('rank', $filters['rank']);
        }
        if ($filters['committee'] === 'any') {
            $query->wherePivotNotNull('committee_role');
        } elseif ($filters['committee'] === 'none') {
            $query->wherePivotNull('committee_role');
        } elseif ($filters['committee'] !== '') {
            $query->wherePivot('committee_role', $filters['committee']);
        }
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('users.name', 'like', $like)
                ->orWhere('users.email', 'like', $like)
                ->orWhere('club_user.member_number', 'like', $like)
                ->orWhereRaw("'MEM-' || users.id like ?", [$like]));
        }

        $toRow = function ($u) use ($club, $invitations) {
            $isJoinRequest = $u->pivot->status === ClubMemberStatus::Pending->value && empty($u->pivot->invitation_token);

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
                'invited_at' => $u->pivot->invited_at ? Carbon::parse($u->pivot->invited_at)->format('j M Y') : null,
                'invitation_accepted_at' => $u->pivot->invitation_accepted_at ? Carbon::parse($u->pivot->invitation_accepted_at)->format('j M Y') : null,
                'joined_at' => $u->pivot->created_at ? Carbon::parse($u->pivot->created_at)->format('j M Y') : 'Recent',
            ];
        };

        $members = $query->orderBy('users.name')->orderBy('users.id')->paginate(25)->withQueryString()
            ->through($toRow);

        $statusCounts = DB::table('club_user')->where('club_id', $club->id)->where('status', '!=', 'deleted')
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [
            'all' => (int) $statusCounts->sum(),
            'active' => (int) ($statusCounts['active'] ?? 0),
            'invited_pending' => (int) ($statusCounts['pending'] ?? 0),
            'deactivated' => (int) ($statusCounts['inactive'] ?? 0),
            'past' => (int) ($statusCounts['past'] ?? 0),
        ];

        // Join requests (not email invitations) are always shown above the table, whatever the filters say.
        $pendingMembers = $club->users()->wherePivot('status', ClubMemberStatus::Pending->value)->wherePivotNull('invitation_token')
            ->orderBy('club_user.created_at')->get()->map($toRow)->values();

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
            'pendingMembers' => $pendingMembers,
            'counts' => $counts,
            'filters' => $filters,
            'enableMemberRanks' => $enableMemberRanks,
            'memberRanks' => $memberRanks,
        ]);
    }

    /**
     * Display detailed profile page for a member in the admin portal.
     */
    public function show(string $clubSlug, int $userId): Response
    {
        $club = $this->findClub($clubSlug);
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
                'due_date' => $i->due_date?->format('j M Y') ?? 'Immediate',
            ]);

        $rsvps = EventRegistration::with(['event', 'attendees'])
            ->where('user_id', $userId)
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->get()
            ->sortByDesc(fn ($r) => $r->event->starts_at)
            ->values()
            ->map(fn (EventRegistration $r) => [
                'event_title' => $r->event->title,
                'event_date' => $r->event->starts_at->format('j M Y, H:i'),
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
            'invited_at' => $memberPivot->invited_at ? Carbon::parse($memberPivot->invited_at)->format('j M Y') : null,
            'invitation_accepted_at' => $memberPivot->invitation_accepted_at ? Carbon::parse($memberPivot->invitation_accepted_at)->format('j M Y') : null,
            'joined_at' => $memberPivot->created_at ? Carbon::parse($memberPivot->created_at)->format('j M Y') : 'Recent',
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
                'total_invoices_paid' => $invoices->where('status', DocumentStatus::Paid->value)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created member in the club roster.
     */
    public function storeMember(StoreMemberRequest $request, string $clubSlug, MemberInvitationService $invitations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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
        $club = $this->findClub($clubSlug);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

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
     * Revoke an active invitation for a member.
     */
    public function revokeInvite(string $clubSlug, int $userId, MemberInvitationService $invitations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $user = $club->users()->where('users.id', $userId)->firstOrFail();

        $invitations->revoke($club, $user);

        return redirect()->back()->with('success', "Invitation revoked for {$user->name}.");
    }
}
