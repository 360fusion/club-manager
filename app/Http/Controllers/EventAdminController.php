<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventRegistration;
use App\Models\EventTicketTier;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Everyone booked on an event: one row per person, guests included.
     */
    public function subscribers(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'menuItems'])
            ->findOrFail($id);

        $registrations = EventRegistration::with(['attendees.ticketTier', 'attendees.starter', 'attendees.main', 'attendees.dessert', 'user'])
            ->where('event_id', $event->id)
            ->orderBy('contact_name')
            ->get();

        $memberRows = DB::table('club_user')
            ->where('club_id', $club->id)
            ->whereIn('user_id', $registrations->pluck('user_id')->filter()->all())
            ->get()
            ->keyBy('user_id');

        $subscribers = $registrations->flatMap(function (EventRegistration $registration) use ($memberRows) {
            $member = $registration->user_id ? $memberRows->get($registration->user_id) : null;

            return $registration->attendees->map(fn (EventAttendee $attendee) => [
                'attendee_id' => $attendee->id,
                'registration_id' => $registration->id,
                'user_id' => $attendee->user_id,
                'name' => $attendee->name,
                'is_guest' => $attendee->is_guest,
                'booked_by' => $attendee->is_guest ? $registration->contact_name : null,
                'email' => $registration->contact_email ?? $registration->user?->email,
                'rank' => $member?->rank ?? '',
                'role' => $member?->role ?? ($registration->user_id ? 'member' : 'guest'),
                'home_club_lodge' => $member?->home_club_name ?? '',
                'member_number' => $member?->member_number ?? '',
                'attendance_status' => $registration->status,
                'attending_dining' => $attendee->attending_dining,
                'menu_selections' => $attendee->mealSummary(),
                'dietary_requirements' => $attendee->dietary_requirements ?? '',
                'payment_status' => $registration->payment_status ?? 'unpaid',
                'amount_paid' => number_format((float) $registration->amount_paid, 2),
                'checked_in_at' => $attendee->checked_in_at?->format('M d, Y @ H:i'),
                'registered_at' => $registration->updated_at?->format('M d, Y @ H:i') ?? '',
                'table_label' => $attendee->table_label,
                'ticket_tier' => $attendee->ticketTier ? [
                    'name' => $attendee->ticketTier->name,
                    'price' => number_format((float) $attendee->ticketTier->price, 2),
                ] : null,
            ]);
        })->values();

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
                'capacity' => $event->capacity,
                'places_taken' => $event->placesTaken(),
                'waitlisted' => $registrations->where('status', 'waitlisted')->sum(fn ($r) => $r->attendees->count()),
            ],
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Update the payment status of a booking.
     */
    public function updateSubscriberPaymentStatus(Request $request, string $clubSlug, int $id, int $registrationId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'payment_status' => 'required|in:paid,unpaid,waived,refunded',
        ]);

        EventRegistration::where('event_id', $event->id)->findOrFail($registrationId)->update([
            'payment_status' => $validated['payment_status'],
        ]);

        return redirect()->back()->with('success', 'Payment status updated successfully.');
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
