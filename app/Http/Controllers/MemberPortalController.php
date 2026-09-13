<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberPortalController extends Controller
{
    /**
     * Display member portal dashboard.
     */
    public function show(string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with(['membershipPlans', 'events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        // Get user's membership pivot status
        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;
        $isPending = $memberPivot && $memberPivot->status === 'pending';

        // Attendance stats
        $userRsvps = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->where('event_user.user_id', $user?->id ?? 0)
            ->get();

        $totalRsvps = $userRsvps->count();
        $attendedCount = $userRsvps->where('attendance_status', 'attending')->count() + $userRsvps->whereNotNull('checked_in_at')->count();
        $attendanceRate = $totalRsvps > 0 ? round(($attendedCount / $totalRsvps) * 100, 1) : 100.0;

        // Upcoming Events
        $events = Event::where('club_id', $club->id)
            ->with(['menuItems', 'ticketTiers'])
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(function ($event) use ($user) {
                $userPivot = $user ? DB::table('event_user')
                    ->where('event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->first() : null;

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'location' => $event->location,
                    'starts_at' => $event->starts_at?->format('M d, Y @ H:i'),
                    'has_dining' => $event->has_dining,
                    'dining_price' => number_format($event->dining_price, 2),
                    'price' => number_format($event->price, 2),
                    'menu_items' => $event->menuItems,
                    'user_rsvp' => $userPivot ? [
                        'attendance_status' => $userPivot->attendance_status,
                        'attending_dining' => (bool) $userPivot->attending_dining,
                        'menu_selections' => json_decode($userPivot->menu_selections ?? '{}', true),
                        'dietary_requirements' => $userPivot->dietary_requirements,
                        'payment_status' => $userPivot->payment_status,
                        'checked_in_at' => $userPivot->checked_in_at,
                    ] : null,
                ];
            });

        // Published Newsletters for member role
        $role = $memberPivot->role ?? 'member';
        $newsletters = Newsletter::where('club_id', $club->id)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->get()
            ->filter(function ($n) use ($role) {
                $target = $n->target_roles ?? [];

                return empty($target) || in_array($role, $target);
            })
            ->map(fn ($n) => [
                'id' => $n->id,
                'subject' => $n->subject,
                'content' => $n->content,
                'sent_at' => $n->sent_at?->format('M d, Y @ H:i'),
            ])
            ->values();

        return Inertia::render('Member/Dashboard', [
            'club' => $club,
            'isPending' => $isPending,
            'memberRole' => $role,
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
            'attendanceRate' => $attendanceRate,
            'attendedCount' => $attendedCount,
            'totalRsvps' => $totalRsvps,
            'events' => $events,
            'newsletters' => $newsletters,
        ]);
    }

    /**
     * Display member clubs listing inside member portal.
     */
    public function myClubs(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $clubs = $user ? $user->clubs()->with('clubType')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'type_name' => $c->clubType?->name ?? 'General',
                'role' => $c->pivot->role ?? 'member',
                'member_number' => $c->pivot->member_number ?? '',
                'status' => $c->pivot->status ?? 'active',
                'joined_at' => $c->pivot->created_at?->format('M d, Y') ?? 'Recent',
            ];
        }) : [];

        return Inertia::render('Member/Clubs', [
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'clubs' => $clubs,
        ]);
    }

    /**
     * Display member events & RSVPs page inside member portal.
     */
    public function events(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with(['events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $events = Event::where('club_id', $club->id)
            ->with(['menuItems', 'ticketTiers'])
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(function ($event) use ($user) {
                $userPivot = $user ? DB::table('event_user')
                    ->where('event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->first() : null;

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'location' => $event->location,
                    'starts_at' => $event->starts_at?->format('M d, Y @ H:i'),
                    'has_dining' => $event->has_dining,
                    'dining_price' => number_format($event->dining_price, 2),
                    'price' => number_format($event->price, 2),
                    'menu_items' => $event->menuItems,
                    'ticket_tiers' => $event->ticketTiers->map(fn ($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'price' => number_format($t->price, 2),
                    ]),
                    'user_rsvp' => $userPivot ? [
                        'attendance_status' => $userPivot->attendance_status,
                        'attending_dining' => (bool) $userPivot->attending_dining,
                        'menu_selections' => json_decode($userPivot->menu_selections ?? '{}', true),
                        'dietary_requirements' => $userPivot->dietary_requirements,
                        'payment_status' => $userPivot->payment_status,
                        'checked_in_at' => $userPivot->checked_in_at,
                    ] : null,
                ];
            });

        return Inertia::render('Member/Events', [
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'events' => $events,
        ]);
    }

    /**
     * Display member dues & invoices page inside member portal.
     */
    public function dues(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with('membershipPlans')
            ->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $invoices = $user ? Invoice::where('club_id', $club->id)
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
            ]) : [];

        $plans = $club->membershipPlans->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => number_format($p->price, 2),
            'billing_period' => $p->billing_period,
        ]);

        return Inertia::render('Member/Dues', [
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
            'plans' => $plans,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display member profile & account security page inside member portal.
     */
    public function profile(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        return Inertia::render('Member/Profile', [
            'club' => $club,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                'two_factor_enabled' => ! empty($user->two_factor_secret),
            ] : null,
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
            'memberProfile' => [
                'role' => $memberPivot->role ?? 'member',
                'member_number' => $memberPivot->member_number ?? 'MEM-1001',
                'phone' => $memberPivot->phone ?? '',
                'emergency_contact' => $memberPivot->emergency_contact ?? '',
                'dietary_notes' => $memberPivot->dietary_notes ?? '',
            ],
        ]);
    }

    /**
     * Update member profile information, avatar photo, and club-specific preferences.
     */
    public function updateProfile(Request $request, string $slug): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $club = Club::where('slug', $slug)->firstOrFail();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar_url' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:4096'],
            'phone' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'dietary_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->fill($request->only('name', 'email'));

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Update club-specific member pivot attributes
        $user->clubs()->updateExistingPivot($club->id, [
            'phone' => $request->input('phone'),
            'emergency_contact' => $request->input('emergency_contact'),
            'dietary_notes' => $request->input('dietary_notes'),
        ]);

        return redirect()->back()->with('success', 'Profile and club membership preferences updated successfully.');
    }

    /**
     * Submit or update event RSVP and 3-course dining selections.
     */
    public function updateRsvp(Request $request, string $slug, int $eventId): RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validate([
            'attendance_status' => 'required|in:attending,declined,tentative',
            'attending_dining' => 'boolean',
            'menu_selections' => 'array',
            'dietary_requirements' => 'nullable|string|max:500',
        ]);

        DB::table('event_user')->updateOrInsert(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'attendance_status' => $validated['attendance_status'],
                'attending_dining' => $validated['attending_dining'] ?? false,
                'menu_selections' => json_encode($validated['menu_selections'] ?? []),
                'dietary_requirements' => $validated['dietary_requirements'] ?? '',
                'updated_at' => now(),
            ]
        );

        return redirect()->back()->with('success', 'RSVP and menu choices updated.');
    }
}
