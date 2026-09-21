<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    /**
     * Display a listing of all registered clubs and their configurations.
     */
    public function index(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('members.dashboard');
        }

        $clubs = Club::with(['clubType', 'membershipPlans'])
            ->withCount([
                'users',
                'events' => fn ($events) => $events->visibleTo(null),
                'posts' => fn ($posts) => $posts->visibleTo(null),
            ])
            ->get()
            ->map(function ($club) {
                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'slug' => $club->slug,
                    'custom_domain' => $club->custom_domain,
                    'domain_status' => $club->domain_status,
                    'status' => $club->status,
                    'type_name' => $club->clubType->name,
                    'type_code' => $club->clubType->code,
                    'available_modules' => $club->clubType->available_modules,
                    'enabled_modules' => $club->settings['enabled_modules'] ?? $club->clubType->available_modules,
                    'tagline' => $club->settings['tagline'] ?? '',
                    'primary_color' => $club->settings['primary_color'] ?? '#3b82f6',
                    'members_count' => $club->users_count,
                    'events_count' => $club->events_count,
                    'posts_count' => $club->posts_count,
                    'plans_count' => $club->membershipPlans->count(),
                ];
            });

        $clubTypes = ClubType::all();

        return Inertia::render('Welcome', [
            'clubs' => $clubs,
            'clubTypes' => $clubTypes,
        ]);
    }

    /**
     * Display a listing of clubs the authenticated user is an admin or member of.
     */
    public function myClubs(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            $clubs = Club::with('clubType')->withCount(['users', 'events'])->get()->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'custom_domain' => $c->custom_domain,
                    'type_name' => $c->clubType?->name ?? 'General',
                    'role' => 'admin',
                    'member_number' => 'OUBC-001',
                    'status' => 'active',
                    'joined_at' => 'Recent',
                    'members_count' => $c->users_count,
                    'events_count' => $c->events_count,
                ];
            });

            return Inertia::render('Admin/Clubs/Index', [
                'clubs' => $clubs,
            ]);
        }

        $clubs = $user->clubs()->with('clubType')->withCount(['users', 'events'])->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'custom_domain' => $c->custom_domain,
                'type_name' => $c->clubType?->name ?? 'General',
                'role' => $c->pivot->role ?? 'member',
                'member_number' => $c->pivot->member_number ?? '',
                'status' => $c->pivot->status ?? 'active',
                'joined_at' => $c->pivot->created_at?->format('M d, Y') ?? 'Recent',
                'members_count' => $c->users_count,
                'events_count' => $c->events_count,
            ];
        });

        return Inertia::render('Admin/Clubs/Index', [
            'clubs' => $clubs,
        ]);
    }

    /**
     * Display a specific club's dashboard & enabled modules.
     */
    public function show(string $slug): Response
    {
        $club = Club::where('slug', $slug)
            ->with(['clubType', 'membershipPlans', 'users', 'newsletters'])
            ->firstOrFail();

        $viewer = Auth::user();
        $isEventStaff = ClubAccess::can($viewer, $club, 'manage_events');
        $visibleEvents = $club->events()->published()->visibleTo($viewer)->with('registrations.attendees')->get();

        $enabledModules = $club->settings['enabled_modules'] ?? $club->clubType->available_modules;

        return Inertia::render('Club/Show', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'custom_domain' => $club->custom_domain,
                'domain_status' => $club->domain_status,
                'status' => $club->status,
                'type_name' => $club->clubType->name,
                'type_code' => $club->clubType->code,
                'available_modules' => $club->clubType->available_modules,
                'enabled_modules' => $enabledModules,
                'tagline' => $club->settings['tagline'] ?? '',
                'primary_color' => $club->settings['primary_color'] ?? '#3b82f6',
                'members' => ! ClubAccess::can($viewer, $club, 'manage_members') ? [] : $club->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->pivot->role,
                    'member_number' => $u->pivot->member_number,
                    'status' => $u->pivot->status ?? 'active',
                ]),
                'plans' => $club->membershipPlans->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'price' => number_format($p->price, 2),
                    'billing_period' => $p->billing_period,
                ]),
                'posts' => $club->posts()->with('author')->published()->visibleTo($viewer)->get()->map(fn ($post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'content' => $post->content,
                    'author_name' => $post->author->name,
                    'published_at' => ($post->published_at ?? $post->created_at)?->format('M d, Y'),
                ]),
                'newsletters' => $club->newsletters->map(fn ($n) => [
                    'id' => $n->id,
                    'subject' => $n->subject,
                    'content' => $n->content,
                    'target_roles' => $n->target_roles,
                    'sent_at' => $n->sent_at?->format('M d, Y H:i'),
                ]),
                'events' => $visibleEvents->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'location' => $event->location,
                    'starts_at' => $event->starts_at?->format('M d, Y @ H:i'),
                    'is_recurring' => $event->is_recurring,
                    'recurrence_rule' => $event->recurrence_rule,
                    'requires_payment' => $event->requires_payment,
                    'price' => number_format($event->price, 2),
                    'has_dining' => $event->has_dining,
                    'dining_price' => number_format($event->dining_price, 2),
                    'rsvp_deadline' => $event->rsvp_deadline?->format('M d, Y'),
                    'status' => $event->status,
                    'ticket_tiers' => $event->ticketTiers->map(fn ($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'price' => number_format($t->price, 2),
                        'max_quantity' => $t->max_quantity,
                        'sold_quantity' => $t->sold_quantity,
                        'available' => $t->max_quantity > 0 ? max(0, $t->max_quantity - $t->sold_quantity) : 'Unlimited',
                    ]),
                    'promos' => $event->promos->map(fn ($p) => [
                        'code' => $p->code,
                        'discount_type' => $p->discount_type,
                        'discount_amount' => $p->discount_amount,
                    ]),
                    'menu_items' => $event->menuItems->map(fn ($m) => [
                        'id' => $m->id,
                        'category' => $m->category,
                        'name' => $m->name,
                        'description' => $m->description,
                        'is_vegetarian' => $m->is_vegetarian,
                        'is_gf' => $m->is_gf,
                    ]),
                    // Names, dietary needs and payment status are for the people running the event only.
                    'attendees' => $isEventStaff
                        ? $event->registrations->where('status', 'attending')->flatMap(fn ($registration) => $registration->attendees->map(fn ($attendee) => [
                            'id' => $attendee->id,
                            'name' => $attendee->name,
                            'attendance_status' => $registration->status,
                            'attending_dining' => $attendee->attending_dining,
                            'menu_selections' => $attendee->mealSummary(),
                            'dietary_requirements' => $attendee->dietary_requirements,
                            'payment_status' => $registration->payment_status,
                            'amount_paid' => number_format((float) $registration->amount_paid, 2),
                            'ticket_qr_code' => 'TICKET-'.strtoupper($club->slug).'-'.$attendee->id,
                        ]))->values()
                        : [],
                ]),
            ],
        ]);
    }

    /**
     * Update custom domain configuration for a club.
     */
    public function updateDomain(Request $request, string $slug)
    {
        $club = Club::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,}$/i', 'unique:clubs,custom_domain,'.$club->id],
        ]);

        $domain = $validated['custom_domain'] ? strtolower(trim($validated['custom_domain'])) : null;

        $club->update([
            'custom_domain' => $domain,
            'domain_status' => $domain ? 'active' : 'pending',
            'domain_verified_at' => $domain ? now() : null,
        ]);

        return redirect()->back();
    }

    /**
     * Approve a pending member invitation request.
     */
    public function approveMember(string $slug, int $userId)
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $approved = $club->users()->updateExistingPivot($userId, ['status' => 'active']);

        if ($approved && $member = User::find($userId)) {
            app(ClubNotifier::class)->toUser($member, ClubNotification::membershipApproved($club));
        }

        return redirect()->back()->with('success', 'Member approved successfully.');
    }

    /**
     * Reject/remove a member invitation request.
     */
    public function rejectMember(string $slug, int $userId)
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $club->users()->detach($userId);

        return redirect()->back()->with('success', 'Member request removed.');
    }
}
