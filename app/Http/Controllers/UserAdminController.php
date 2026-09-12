<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
                'member_number' => $u->pivot->member_number ?? ('MEM-'.$u->id),
                'status' => $u->pivot->status ?? 'active',
                'joined_at' => $u->pivot->created_at?->format('M d, Y') ?? 'Recent',
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'club' => $club,
            'members' => $members,
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

        // Joined clubs list for this user
        $userClubs = $user->clubs()->with('clubType')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'role' => $c->pivot->role ?? 'member',
                'status' => $c->pivot->status ?? 'active',
            ];
        });

        // Event RSVPs for this club
        $rsvps = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->where('event_user.user_id', $user->id)
            ->select('events.title', 'events.starts_at', 'events.location', 'event_user.*')
            ->orderByDesc('events.starts_at')
            ->get()
            ->map(fn ($r) => [
                'event_title' => $r->title,
                'starts_at' => $r->starts_at ? Carbon::parse($r->starts_at)->format('M d, Y @ H:i') : 'TBD',
                'location' => $r->location,
                'attendance_status' => $r->attendance_status,
                'attending_dining' => (bool) $r->attending_dining,
                'menu_selections' => json_decode($r->menu_selections ?? '{}', true),
                'dietary_requirements' => $r->dietary_requirements,
                'checked_in_at' => $r->checked_in_at ? Carbon::parse($r->checked_in_at)->format('M d, Y @ H:i') : null,
            ]);

        // Attendance stats
        $totalRsvps = $rsvps->count();
        $attendedCount = $rsvps->where('attendance_status', 'attending')->count();
        $attendanceRate = $totalRsvps > 0 ? round(($attendedCount / $totalRsvps) * 100, 1) : 100.0;

        // Invoices & Dues for this club
        $invoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'title' => $inv->title,
                'amount' => number_format($inv->amount, 2),
                'status' => $inv->status,
                'paid_at' => $inv->paid_at?->format('M d, Y'),
                'created_at' => $inv->created_at?->format('M d, Y'),
            ]);

        $memberData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $memberPivot->role ?? 'member',
            'member_number' => $memberPivot->member_number ?? ('MEM-'.$user->id),
            'status' => $memberPivot->status ?? 'active',
            'joined_at' => $memberPivot->created_at?->format('M d, Y') ?? 'Recent',
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
            'member_number' => 'nullable|string|max:100',
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'name' => $validated['name'],
                'password' => Hash::make('password123'),
            ]
        );

        if ($club->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'User is already a member of this club.');
        }

        $club->users()->attach($user->id, [
            'role' => $validated['role'],
            'member_number' => $validated['member_number'] ?: ('MEM-'.rand(1000, 9999)),
            'status' => 'active',
        ]);

        return redirect()->back()->with('success', 'Member added successfully to roster.');
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
     * Remove a member from the club.
     */
    public function removeMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->users()->detach($userId);

        return redirect()->back()->with('success', 'Member removed from roster.');
    }
}
