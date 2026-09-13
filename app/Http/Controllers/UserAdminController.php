<?php

namespace App\Http\Controllers;

use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    /**
     * Display member roster for a club in the admin portal.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['users'])
            ->firstOrFail();

        $members = $club->users->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role ?? 'member',
                'rank' => $u->pivot->rank ?? '',
                'member_number' => $u->pivot->member_number ?? ('MEM-'.$u->id),
                'status' => $u->pivot->status ?? 'active',
                'invitation_token' => $u->pivot->invitation_token ?? null,
                'invited_at' => $u->pivot->invited_at ? Carbon::parse($u->pivot->invited_at)->format('M d, Y') : null,
                'invitation_accepted_at' => $u->pivot->invitation_accepted_at ? Carbon::parse($u->pivot->invitation_accepted_at)->format('M d, Y') : null,
                'joined_at' => $u->pivot->created_at ? Carbon::parse($u->pivot->created_at)->format('M d, Y') : 'Recent',
            ];
        });

        $enableMemberRanks = $club->settings['enable_member_ranks'] ?? true;
        $memberRanks = $club->settings['member_ranks'] ?? ['Novice', 'Intermediate', 'Senior', 'Captain', 'Coxswain', 'Veteran'];

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
        $user = User::findOrFail($userId);

        $memberPivot = $user->clubs()->where('clubs.id', $club->id)->first()?->pivot;

        $invoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->invoice_number,
                'amount_formatted' => $i->currency.' '.number_format($i->amount, 2),
                'status' => $i->status,
                'due_date' => $i->due_date?->format('M d, Y') ?? 'Immediate',
            ]);

        $rsvps = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->where('event_user.user_id', $userId)
            ->select('events.title', 'events.starts_at', 'events.location', 'event_user.*')
            ->orderByDesc('events.starts_at')
            ->get()
            ->map(fn ($r) => [
                'event_title' => $r->title,
                'event_date' => $r->starts_at ? Carbon::parse($r->starts_at)->format('M d, Y @ H:i') : 'TBD',
                'location' => $r->location,
                'rsvp_status' => $r->attendance_status ?? 'attending',
                'attended' => ! empty($r->checked_in_at),
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
            'invitation_token' => $memberPivot->invitation_token ?? null,
            'invited_at' => $memberPivot->invited_at ? Carbon::parse($memberPivot->invited_at)->format('M d, Y') : null,
            'invitation_accepted_at' => $memberPivot->invitation_accepted_at ? Carbon::parse($memberPivot->invitation_accepted_at)->format('M d, Y') : null,
            'joined_at' => $memberPivot->created_at ? Carbon::parse($memberPivot->created_at)->format('M d, Y') : 'Recent',
            'two_factor_enabled' => ! empty($user->two_factor_secret),
        ];

        return Inertia::render('Admin/Users/Show', [
            'club' => $club,
            'member' => $memberData,
            'userClubs' => $userClubs,
            'rsvps' => $rsvps,
            'invoices' => $invoices,
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
    public function storeMember(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|in:owner,admin,coach,member,treasurer',
            'rank' => 'nullable|string|max:100',
            'member_number' => 'nullable|string|max:100',
            'send_invite' => 'nullable|boolean',
        ]);

        $sendInvite = $request->boolean('send_invite', true);
        $token = $sendInvite ? Str::random(40) : null;

        $user = User::firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'name' => $validated['name'],
                'password' => Hash::make(Str::random(16)),
            ]
        );

        if ($club->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'User is already a member of this club.');
        }

        $club->users()->attach($user->id, [
            'role' => $validated['role'],
            'rank' => $validated['rank'] ?? null,
            'member_number' => ($validated['member_number'] ?? null) ?: ('MEM-'.rand(1000, 9999)),
            'status' => 'active',
            'invitation_token' => $token,
            'invited_at' => $sendInvite ? now() : null,
        ]);

        if ($sendInvite && $token) {
            $acceptUrl = route('invitation.accept', ['slug' => $club->slug, 'token' => $token]);
            try {
                Mail::to($user->email)->send(new MemberInvitationMail($club, $user, $token, $acceptUrl));
                return redirect()->back()->with('success', "Member added to roster & invitation email sent to {$user->email}.");
            } catch (\Exception $e) {
                return redirect()->back()->with('success', "Member added to roster. Invitation link: {$acceptUrl}");
            }
        }

        return redirect()->back()->with('success', 'Member added successfully to roster.');
    }

    /**
     * Send or resend an email invitation to a member.
     */
    public function sendInvite(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::findOrFail($userId);

        $memberPivot = $user->clubs()->where('clubs.id', $club->id)->first()?->pivot;
        if (! $memberPivot) {
            return redirect()->back()->with('error', 'User is not a member of this club.');
        }

        $token = Str::random(40);

        $club->users()->updateExistingPivot($userId, [
            'invitation_token' => $token,
            'invited_at' => now(),
        ]);

        $acceptUrl = route('invitation.accept', ['slug' => $club->slug, 'token' => $token]);

        try {
            Mail::to($user->email)->send(new MemberInvitationMail($club, $user, $token, $acceptUrl));
            return redirect()->back()->with('success', "Invitation email sent successfully to {$user->email}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('success', "Invitation token created. Share activation link: {$acceptUrl}");
        }
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
     * Revoke an active invitation for a member.
     */
    public function revokeInvite(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::findOrFail($userId);

        $club->users()->updateExistingPivot($userId, [
            'invitation_token' => null,
            'invited_at' => null,
        ]);

        return redirect()->back()->with('success', "Invitation revoked for {$user->name}.");
    }

    /**
     * Update member status (active, inactive, past, pending).
     */
    public function updateStatus(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,past,pending',
        ]);

        $club->users()->updateExistingPivot($userId, ['status' => $validated['status']]);

        $messages = [
            'active' => "{$user->name} has been restored and reactivated on the active roster.",
            'inactive' => "{$user->name} status set to Deactivated (access paused).",
            'past' => "{$user->name} moved to Past Members list.",
            'pending' => "{$user->name} status set to pending.",
        ];

        return redirect()->back()->with('success', $messages[$validated['status']] ?? 'Member status updated.');
    }

    /**
     * Remove a member from active roster to Past Members.
     */
    public function removeMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::findOrFail($userId);

        $club->users()->updateExistingPivot($userId, ['status' => 'past']);

        return redirect()->back()->with('success', "{$user->name} moved to Past Members list.");
    }

    /**
     * Permanently delete a member from the club database.
     */
    public function forceDeleteMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::findOrFail($userId);

        $club->users()->detach($userId);

        return redirect()->back()->with('success', "{$user->name} permanently removed from club database.");
    }
}
