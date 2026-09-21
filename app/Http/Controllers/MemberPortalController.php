<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Newsletter;
use App\Models\Post;
use App\Services\Events\EventOnlinePayment;
use App\Services\Events\EventPayload;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
use App\Support\EmailVerification;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MemberPortalController extends Controller
{
    /**
     * Display member portal dashboard.
     */
    public function show(string $slug): Response|RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with(['membershipPlans', 'events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        // Get user's membership pivot status
        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;
        $isPending = $memberPivot && $memberPivot->status === 'pending';

        // The club root is the member area. Everyone else sees the public website.
        if ($memberPivot === null && ! $user?->is_super_admin) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Attendance stats
        $userRsvps = EventRegistration::with('attendees')
            ->where('user_id', $user?->id ?? 0)
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->get();

        $totalRsvps = $userRsvps->count();
        $attendedCount = $userRsvps->where('status', 'attending')->count() + $userRsvps->filter(fn ($r) => $r->booker()?->checked_in_at !== null)->count();
        $attendanceRate = $totalRsvps > 0 ? round(($attendedCount / $totalRsvps) * 100, 1) : 100.0;

        // Published Meetings & Summons for the member
        $meetings = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(function ($meeting) use ($user) {
                $userRsvp = $user ? MeetingRsvp::where('meeting_id', $meeting->id)
                    ->where('user_id', $user->id)
                    ->with('guests')
                    ->first() : null;

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meeting_date' => $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('M d, Y') : '',
                    'raw_meeting_date' => $meeting->meeting_date,
                    'starts_at' => $meeting->starts_at,
                    'rehearsal_starts_at' => $meeting->rehearsal_starts_at,
                    'venue' => $meeting->venue,
                    'dress_code' => $meeting->dress_code,
                    'rsvp_cutoff_at' => $meeting->rsvp_cutoff_at ? Carbon::parse($meeting->rsvp_cutoff_at)->format('M d, Y @ H:i') : null,
                    'is_cutoff_passed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
                    'dining_cost_member' => number_format($meeting->dining_cost_member, 2),
                    'user_rsvp' => $userRsvp ? [
                        'attendance_status' => $userRsvp->attendance_status,
                        'dietary_requirements' => $userRsvp->dietary_requirements,
                        'apology_reason' => $userRsvp->apology_reason,
                        'payment_status' => $userRsvp->payment_status ?? 'unpaid',
                        'payment_reference' => $userRsvp->payment_reference,
                        'guests' => $userRsvp->guests,
                    ] : null,
                ];
            });

        // Upcoming Events
        $eventModels = Event::where('club_id', $club->id)
            ->published()
            ->visibleTo($user)
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->orderBy('starts_at', 'asc')
            ->get();
        $eventRegistrations = EventPayload::registrationsFor($user, $eventModels->pluck('id'));
        $events = $eventModels->map(fn (Event $event) => EventPayload::forMember($event, $eventRegistrations->get($event->id)));

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
            'meetings' => $meetings,
            'events' => $events,
            'newsletters' => $newsletters,
        ]);
    }

    /**
     * Display member events & RSVPs page inside member portal.
     */
    public function events(Request $request, string $slug): Response|RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with(['events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        if ($memberPivot === null && ! $user?->is_super_admin) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Published Meetings & Summons for the member
        $meetings = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(function ($meeting) use ($user) {
                $userRsvp = $user ? MeetingRsvp::where('meeting_id', $meeting->id)
                    ->where('user_id', $user->id)
                    ->with('guests')
                    ->first() : null;

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meeting_date' => $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('M d, Y') : '',
                    'raw_meeting_date' => $meeting->meeting_date,
                    'starts_at' => $meeting->starts_at,
                    'rehearsal_starts_at' => $meeting->rehearsal_starts_at,
                    'venue' => $meeting->venue,
                    'dress_code' => $meeting->dress_code,
                    'rsvp_cutoff_at' => $meeting->rsvp_cutoff_at ? Carbon::parse($meeting->rsvp_cutoff_at)->format('M d, Y @ H:i') : null,
                    'is_cutoff_passed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
                    'dining_cost_member' => number_format($meeting->dining_cost_member, 2),
                    'user_rsvp' => $userRsvp ? [
                        'attendance_status' => $userRsvp->attendance_status,
                        'dietary_requirements' => $userRsvp->dietary_requirements,
                        'apology_reason' => $userRsvp->apology_reason,
                        'payment_status' => $userRsvp->payment_status ?? 'unpaid',
                        'payment_reference' => $userRsvp->payment_reference,
                        'guests' => $userRsvp->guests,
                    ] : null,
                ];
            });

        $eventModels = Event::where('club_id', $club->id)
            ->published()
            ->visibleTo($user)
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->orderBy('starts_at', 'asc')
            ->get();
        $eventRegistrations = EventPayload::registrationsFor($user, $eventModels->pluck('id'));
        $events = $eventModels->map(fn (Event $event) => EventPayload::forMember($event, $eventRegistrations->get($event->id)));

        return Inertia::render('Member/Events', [
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'meetings' => $meetings,
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

        abort_unless(ClubAccess::isActiveMember(Auth::user(), $club), 403, 'You are not a member of this club.');

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $invoices = $user ? Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
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
        abort_if($memberPivot === null && ! $user?->is_super_admin, 403, 'You are not a member of this club.');

        $accMember = $user ? Member::where('club_id', $club->id)->where('user_id', $user->id)->first() : null;

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
                'masonic_rank' => $accMember?->masonic_rank ?? 'Bro',
                'grand_rank' => $accMember?->grand_rank ?? null,
                'provincial_rank' => $accMember?->provincial_rank ?? null,
                'grand_lodge_number' => $accMember?->grand_lodge_number ?? null,
                'date_of_initiation' => $accMember?->date_of_initiation?->format('d M Y') ?? null,
                'date_of_passing' => $accMember?->date_of_passing?->format('d M Y') ?? null,
                'date_of_raising' => $accMember?->date_of_raising?->format('d M Y') ?? null,
                'date_of_joining' => $accMember?->date_of_joining?->format('d M Y') ?? null,
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

        abort_unless($user->clubs()->where('clubs.id', $club->id)->exists(), 403, 'You are not a member of this club.');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:#^(https?://|avatars/)#i'],
            'avatar' => UploadRules::image(4096),
            'phone' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'dietary_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->fill($request->only('name', 'email'));

        if ($request->hasFile('avatar')) {
            ImageDownscaler::apply($request->file('avatar'), 800);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        $emailChanged = $user->isDirty('email');

        if ($emailChanged && EmailVerification::required()) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && EmailVerification::required()) {
            $user->sendEmailVerificationNotification();
        }

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
    public function updateRsvp(Request $request, string $slug, int $eventId, EventRegistrationService $registrations, EventOnlinePayment $online): RedirectResponse|SymfonyResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->published()->findOrFail($eventId);

        abort_unless($event->canBeRsvpedBy($user), 403, 'This event is not open to your account.');

        $validated = $request->validate([
            'attendance_status' => 'required|in:attending,declined,tentative',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|integer|max:4294967295',
            'promo_code' => 'nullable|string|max:40',
            'attendees' => 'nullable|array|max:50',
            'attendees.*.name' => 'nullable|string|max:150',
            'attendees.*.email' => 'nullable|string|max:255',
            'attendees.*.is_guest' => 'nullable|boolean',
            'attendees.*.ticket_tier_id' => 'nullable|integer',
            'attendees.*.attending_dining' => 'nullable|boolean',
            'attendees.*.starter_item_id' => 'nullable|integer',
            'attendees.*.main_item_id' => 'nullable|integer',
            'attendees.*.dessert_item_id' => 'nullable|integer',
            'attendees.*.dietary_requirements' => 'nullable|string|max:1000',
        ]);

        $registration = $registrations->register($event, $user, [
            'status' => $validated['attendance_status'],
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'promo_code' => $validated['promo_code'] ?? null,
            'attendees' => $validated['attendees'] ?? [],
        ]);

        $registrations->sendGuestConfirmations($registration);

        // Chose to pay online (card or PayPal): straight on to the payment page for what they owe.
        if ($registration->status === 'attending' && $registration->paymentMethod?->isOnline() && $online->canPay($registration)) {
            $back = route('member.events', ['slug' => $club->slug]);

            return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled', null, route('member.events.paypal_return', ['slug' => $club->slug, 'id' => $event->id])));
        }

        return redirect()->back()->with('success', $validated['attendance_status'] === 'declined' ? 'Your reply has been saved.' : 'Your booking has been saved.');
    }

    /**
     * Record or update meeting RSVP for authenticated member.
     */
    public function updateMeetingRsvp(Request $request, string $slug, int $id): RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();

        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        if ($meeting->rsvp_cutoff_at && Carbon::now()->isAfter($meeting->rsvp_cutoff_at)) {
            return redirect()->back()->withErrors(['cutoff' => 'The dining deadline for this meeting has passed. Please contact the Secretary directly.']);
        }

        $validated = $request->validate([
            'attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies',
            'apology_reason' => 'nullable|string|max:1000',
            'dietary_requirements' => 'nullable|string|max:1000',
            'guests' => 'nullable|array|max:'.$club->maxGuestsPerMember(),
            'guests.*.guest_name' => 'nullable|string|max:150',
            'guests.*.dietary_requirements' => 'nullable|string|max:1000',
            'guests.*.attending_dining' => 'nullable|boolean',
        ]);

        $surname = strtoupper(last(explode(' ', $user->name)));
        $paymentRef = ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname;

        $rsvp = MeetingRsvp::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            [
                'token_hash' => Str::random(40),
                'token_expires_at' => Carbon::now()->addDays(30),
                'attendance_status' => $validated['attendance_status'],
                'apology_reason' => $validated['apology_reason'] ?? null,
                'dietary_requirements' => $validated['dietary_requirements'] ?? null,
                'payment_reference' => $paymentRef,
                'responded_at' => Carbon::now(),
            ]
        );

        if (array_key_exists('guests', $validated)) {
            $rsvp->guests()->delete();
            if (is_array($validated['guests'])) {
                foreach ($validated['guests'] as $g) {
                    if (! empty($g['guest_name'])) {
                        $rsvp->guests()->create([
                            'guest_name' => $g['guest_name'],
                            'dietary_requirements' => $g['dietary_requirements'] ?? null,
                            'attending_dining' => $g['attending_dining'] ?? true,
                            'dining_fee' => $meeting->dining_cost_guest,
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Your RSVP response has been saved.');
    }

    /**
     * Display vertical web page view for a meeting summons.
     */
    public function summons(string $slug, int $id): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();

        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user', 'fraternalVisits'])
            ->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        $members = $club->users()->wherePivot('role', '!=', 'visitor')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'rank' => $u->pivot->rank ?? '',
                'joined_year' => $u->pivot->joined_year ?? $u->created_at?->format('Y'),
            ];
        });

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $userRsvp = $user ? MeetingRsvp::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->with('guests')
            ->first() : null;

        $secretaryUser = $club->users()->wherePivot('role', 'secretary')->first() ?: $club->users->first();
        $worshipfulMaster = $club->users()->wherePivot('role', 'master')->first() ?: $club->users->first();

        $charityGrants = CharityGrant::where('club_id', $club->id)
            ->with(['proposer', 'seconder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Member/Meetings/Summons', [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $members,
            'userRsvp' => $userRsvp,
            'secretaryUser' => $secretaryUser,
            'worshipfulMaster' => $worshipfulMaster,
            'memberRole' => $memberPivot->role ?? 'member',
            'isCutoffPassed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
            'charityGrants' => $charityGrants,
        ]);
    }

    /**
     * Download or view PDF version of the meeting summons.
     */
    public function downloadMeetingPdf(string $slug, int $id)
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();
        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');

        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        $adminController = app(MeetingAdminController::class);
        request()->merge(['download' => 1]);

        return $adminController->pdf($slug, $id);
    }

    /**
     * Display a published news article inside the member portal.
     */
    public function showPost(string $slug, int $id): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();
        $post = Post::where('club_id', $club->id)->published()->visibleTo($user)->findOrFail($id);

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        return Inertia::render('Member/PostShow', [
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'blocks' => $post->blocks ?? [],
                'attachments' => $post->attachments ?? [],
                'cover_image_url' => $post->cover_image_url,
                'published_at' => ($post->published_at ?? $post->created_at)?->format('M d, Y'),
                'author_name' => $post->author?->name ?? 'Club Secretary',
            ],
        ]);
    }
}
