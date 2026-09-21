<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventTicketTier;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventAdminController extends Controller
{
    /**
     * Display a listing of club events in admin dashboard.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $events = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'promos', 'menuItems'])
            ->withCount('attendees')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'club' => $club,
            'events' => $events,
        ]);
    }

    /**
     * Display subscribers/RSVPs for a specific event.
     */
    public function subscribers(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'menuItems'])
            ->findOrFail($id);

        $subscribers = DB::table('event_user')
            ->join('users', 'users.id', '=', 'event_user.user_id')
            ->leftJoin('club_user', function ($join) use ($club) {
                $join->on('club_user.user_id', '=', 'users.id')
                    ->where('club_user.club_id', '=', $club->id);
            })
            ->leftJoin('event_ticket_tiers', 'event_ticket_tiers.id', '=', 'event_user.ticket_tier_id')
            ->where('event_user.event_id', '=', $event->id)
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
                'club_user.rank',
                'club_user.role as member_role',
                'club_user.home_club_name as home_club_lodge',
                'club_user.member_number',
                'event_user.attendance_status',
                'event_user.attending_dining',
                'event_user.menu_selections',
                'event_user.dietary_requirements',
                'event_user.payment_status',
                'event_user.amount_paid',
                'event_user.checked_in_at',
                'event_user.updated_at as registered_at',
                'event_ticket_tiers.name as ticket_tier_name',
                'event_ticket_tiers.price as ticket_tier_price',
            ])
            ->orderBy('users.name', 'asc')
            ->get()
            ->map(function ($sub) {
                $menuSelections = json_decode($sub->menu_selections ?? '{}', true);

                return [
                    'user_id' => $sub->user_id,
                    'name' => $sub->name,
                    'email' => $sub->email,
                    'rank' => $sub->rank ?? '',
                    'role' => $sub->member_role ?? 'member',
                    'home_club_lodge' => $sub->home_club_lodge ?? '',
                    'member_number' => $sub->member_number ?? '',
                    'attendance_status' => $sub->attendance_status,
                    'attending_dining' => (bool) $sub->attending_dining,
                    'menu_selections' => is_array($menuSelections) ? $menuSelections : [],
                    'dietary_requirements' => $sub->dietary_requirements ?? '',
                    'payment_status' => $sub->payment_status ?? 'unpaid',
                    'amount_paid' => number_format((float) $sub->amount_paid, 2),
                    'checked_in_at' => $sub->checked_in_at ? Carbon::parse($sub->checked_in_at)->format('M d, Y @ H:i') : null,
                    'registered_at' => $sub->registered_at ? Carbon::parse($sub->registered_at)->format('M d, Y @ H:i') : '',
                    'ticket_tier' => $sub->ticket_tier_name ? [
                        'name' => $sub->ticket_tier_name,
                        'price' => number_format((float) $sub->ticket_tier_price, 2),
                    ] : null,
                ];
            });

        return Inertia::render('Admin/Events/Subscribers', [
            'club' => $club,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->format('M d, Y @ H:i'),
                'location' => $event->formatted_location ?: $event->location,
                'has_dining' => $event->has_dining,
                'dining_price' => number_format((float) $event->dining_price, 2),
                'price' => number_format((float) $event->price, 2),
                'requires_payment' => $event->requires_payment,
            ],
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Update payment status of a specific event subscriber.
     */
    public function updateSubscriberPaymentStatus(Request $request, string $clubSlug, int $id, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'payment_status' => 'required|in:paid,unpaid,waived,refunded',
        ]);

        DB::table('event_user')
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->update([
                'payment_status' => $validated['payment_status'],
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Subscriber payment status updated successfully.');
    }

    /**
     * Show form for creating or editing an event.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $event = $id
            ? Event::where('club_id', $club->id)->with(['ticketTiers', 'promos', 'menuItems'])->findOrFail($id)
            : new Event([
                'club_id' => $club->id,
                'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'requires_payment' => true,
                'has_dining' => false,
                'status' => 'upcoming',
                'visibility' => Visibility::Club,
                'rsvp_audience' => Visibility::Club,
            ]);

        return Inertia::render('Admin/Events/Form', [
            'club' => $club,
            'event' => $event,
            'visibilityOptions' => Visibility::options(),
        ]);
    }

    /**
     * Store or update an event with ticket tiers, promos, and dining items.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'location' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:50',
            'starts_at' => 'required|date',
            'requires_payment' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:99999999.99',
            'has_dining' => 'boolean',
            'dining_price' => 'nullable|numeric|min:0|max:99999999.99',
            'booking_cutoff_days' => 'nullable|integer|min:0|max:1000000',
            'status' => 'required|in:upcoming,completed,cancelled',
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'rsvp_audience' => ['nullable', Rule::enum(Visibility::class)],
            'ticket_tiers' => 'array|max:100',
            'promos' => 'array|max:100',
            'menu_items' => 'array|max:100',
        ]);

        $existing = ! empty($validated['id']) ? Event::where('club_id', $club->id)->find($validated['id']) : null;
        $visibility = Visibility::from($validated['visibility'] ?? $existing?->visibility->value ?? Visibility::Club->value);
        $rsvpAudience = Visibility::from($validated['rsvp_audience'] ?? $existing?->rsvp_audience->value ?? Visibility::Club->value);

        if (! $rsvpAudience->isNoBroaderThan($visibility)) {
            throw ValidationException::withMessages([
                'rsvp_audience' => 'People who cannot see this event cannot RSVP to it. Choose an RSVP audience that is no wider than who can see the event.',
            ]);
        }

        $addressParts = array_filter([
            $validated['address_line_1'] ?? null,
            $validated['address_line_2'] ?? null,
            $validated['city'] ?? null,
            $validated['county'] ?? null,
            $validated['postcode'] ?? null,
        ]);
        $location = ! empty($addressParts) ? implode(', ', $addressParts) : ($validated['location'] ?? '');
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);

        $event = Event::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'title' => $validated['title'],
                'slug' => $slug ?: Str::slug($validated['title']),
                'description' => $validated['description'] ?? '',
                'location' => $location,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'] ?? null,
                'county' => $validated['county'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'starts_at' => $validated['starts_at'],
                'requires_payment' => $validated['requires_payment'] ?? true,
                'price' => $validated['price'] ?? 0,
                'has_dining' => $validated['has_dining'] ?? false,
                'dining_price' => $validated['dining_price'] ?? 0,
                'booking_cutoff_days' => isset($validated['booking_cutoff_days']) ? (int) $validated['booking_cutoff_days'] : null,
                'status' => $validated['status'] ?? 'upcoming',
                'visibility' => $visibility,
                'rsvp_audience' => $rsvpAudience,
            ]
        );

        if ($event->wasRecentlyCreated && $event->status === 'upcoming') {
            app(ClubNotifier::class)->toMembers($club, ClubNotification::event($event, $club), $request->user());
        }

        // Sync Ticket Tiers
        if (isset($validated['ticket_tiers'])) {
            $event->ticketTiers()->delete();
            foreach ($validated['ticket_tiers'] as $tier) {
                if (! empty($tier['name'])) {
                    EventTicketTier::create([
                        'event_id' => $event->id,
                        'name' => $tier['name'],
                        'price' => $tier['price'] ?? 0,
                        'max_quantity' => $tier['max_quantity'] ?? 100,
                        'sold_quantity' => $tier['sold_quantity'] ?? 0,
                    ]);
                }
            }
        }

        // Sync Promos
        if (isset($validated['promos'])) {
            $event->promos()->delete();
            foreach ($validated['promos'] as $promo) {
                if (! empty($promo['code'])) {
                    EventPromo::create([
                        'event_id' => $event->id,
                        'code' => strtoupper($promo['code']),
                        'discount_type' => 'percent',
                        'discount_amount' => $promo['discount_amount'] ?? 10,
                        'max_uses' => $promo['max_uses'] ?? 50,
                    ]);
                }
            }
        }

        // Sync Menu Items
        if (isset($validated['menu_items']) && $event->has_dining) {
            $event->menuItems()->delete();
            foreach ($validated['menu_items'] as $item) {
                if (! empty($item['name'])) {
                    EventMenuItem::create([
                        'event_id' => $event->id,
                        'category' => $item['category'] ?? 'main',
                        'name' => $item['name'],
                        'description' => $item['description'] ?? '',
                    ]);
                }
            }
        }

        return redirect()->route('admin.events.index', ['clubSlug' => $club->slug])
            ->with('success', 'Event saved successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $event->delete();

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }
}
