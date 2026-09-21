<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventRegistration;
use App\Models\EventTicketTier;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
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
                'email' => $attendee->email ?? $registration->contact_email ?? $registration->user?->email,
                'rank' => $member?->rank ?? '',
                'role' => $member?->role ?? ($registration->user_id ? 'member' : 'guest'),
                'home_club_lodge' => $member?->home_club_name ?? '',
                'member_number' => $member?->member_number ?? '',
                'attendance_status' => $registration->status,
                'attending_dining' => $attendee->attending_dining,
                'menu_selections' => $attendee->mealSummary(),
                'dietary_requirements' => $attendee->dietary_requirements ?? '',
                'payment_status' => $registration->payment_status ?? 'unpaid',
                'total' => number_format((float) $registration->total, 2, '.', ''),
                'balance' => number_format($registration->balanceDue(), 2, '.', ''),
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
            'canManagePayments' => ClubAccess::can(request()->user(), $club, 'manage_billing'),
        ]);
    }

    /**
     * Show form for creating or editing an event.
     */
    public function edit(string $clubSlug, EventPricing $pricing, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $event = $id
            ? Event::where('club_id', $club->id)->with(['ticketTiers', 'promos', 'menuItems'])->findOrFail($id)
            : new Event([
                'club_id' => $club->id,
                'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'requires_payment' => true,
                'has_dining' => false,
                'status' => 'draft',
                'visibility' => Visibility::Club,
                'rsvp_audience' => Visibility::Club,
            ]);

        return Inertia::render('Admin/Events/Form', [
            'club' => $club,
            'event' => $event,
            'visibilityOptions' => Visibility::options(),
            // Ticket types and dishes in use cannot be removed without losing people's choices.
            'tiersInUse' => $id ? EventAttendee::whereIn('ticket_tier_id', $event->ticketTiers->pluck('id'))->pluck('ticket_tier_id')->unique()->values() : [],
            'dishesInUse' => $id ? $this->dishesInUse($event) : [],
            'registrationCount' => $id ? $event->registrations()->whereIn('status', EventRegistration::HOLDING_PLACE)->count() : 0,
            'clubPaymentMethods' => ClubPaymentMethod::where('club_id', $club->id)->where('is_active', true)->orderBy('sort_order')->get(['id', 'type', 'label', 'default_adjustment_kind', 'default_adjustment_mode', 'default_adjustment_amount', 'default_adjustment_scope', 'due_days', 'due_basis']),
            'enabledMethods' => $id ? $event->paymentMethods()->get(['payment_method_id', 'is_enabled', 'adjustment_kind', 'adjustment_mode', 'adjustment_amount', 'adjustment_scope', 'due_days', 'due_basis']) : [],
            'pricePreview' => $id ? $this->pricePreview($event, $pricing) : null,
        ]);
    }

    /**
     * Ids of dishes someone has already chosen.
     *
     * @return list<int>
     */
    private function dishesInUse(Event $event): array
    {
        $ids = $event->menuItems->pluck('id');

        return EventAttendee::query()
            ->where(fn ($q) => $q->whereIn('starter_item_id', $ids)->orWhereIn('main_item_id', $ids)->orWhereIn('dessert_item_id', $ids))
            ->get(['starter_item_id', 'main_item_id', 'dessert_item_id'])
            ->flatMap(fn ($a) => [$a->starter_item_id, $a->main_item_id, $a->dessert_item_id])
            ->filter()->unique()->values()->all();
    }

    /**
     * Store or update an event with ticket tiers, promos, and dining items.
     */
    public function store(Request $request, string $clubSlug, EventPricing $pricing): RedirectResponse
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
            'status' => 'required|in:draft,upcoming,completed,cancelled',
            'ends_at' => 'nullable|date|after:starts_at',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'waitlist_enabled' => 'boolean',
            'registration_opens_at' => 'nullable|date',
            'rsvp_deadline' => 'nullable|date',
            'allow_public_registration' => 'boolean',
            'max_guests_per_booking' => 'nullable|integer|min:0|max:50',
            'cancellation_policy' => 'nullable|string|max:5000',
            'booking_fee_type' => 'nullable|in:none,fixed,percent',
            'booking_fee_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'booking_fee_scope' => 'nullable|in:per_booking,per_ticket',
            'booking_fee_label' => 'nullable|string|max:60',
            'price_display' => 'nullable|in:standard,all_in',
            'payment_methods' => 'nullable|array|max:20',
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'rsvp_audience' => ['nullable', Rule::enum(Visibility::class)],
            'ticket_tiers' => 'array|max:100',
            'promos' => 'array|max:100',
            'menu_items' => 'array|max:100',
        ]);

        // Everything is saved together or not at all, so an error part-way leaves nothing behind.
        DB::beginTransaction();

        try {
            $request->validate([
                'payment_methods.*.payment_method_id' => 'required_with:payment_methods|integer',
                'payment_methods.*.is_enabled' => 'nullable|boolean',
                'payment_methods.*.adjustment_kind' => 'nullable|in:none,discount,fee',
                'payment_methods.*.adjustment_mode' => 'nullable|in:percent,fixed',
                'payment_methods.*.adjustment_amount' => 'nullable|numeric|min:0|max:99999999.99',
                'payment_methods.*.adjustment_scope' => 'nullable|in:per_person,per_booking',
                'payment_methods.*.due_days' => 'nullable|integer|min:0|max:365',
                'payment_methods.*.due_basis' => 'nullable|in:before_event,after_booking',
                'ticket_tiers.*.id' => 'nullable|integer',
                'ticket_tiers.*.name' => 'nullable|string|max:150',
                'ticket_tiers.*.price' => 'nullable|numeric|min:0|max:99999999.99',
                'ticket_tiers.*.max_quantity' => 'nullable|integer|min:0|max:100000',
                'ticket_tiers.*.audience' => 'nullable|in:all,member,guest,public',
                'menu_items.*.id' => 'nullable|integer',
                'menu_items.*.category' => 'nullable|in:starter,main,dessert',
                'menu_items.*.name' => 'nullable|string|max:150',
                'menu_items.*.description' => 'nullable|string|max:500',
                'menu_items.*.allergens' => 'nullable|string|max:255',
                'menu_items.*.is_vegetarian' => 'nullable|boolean',
                'menu_items.*.is_vegan' => 'nullable|boolean',
                'menu_items.*.is_gf' => 'nullable|boolean',
            ]);

            $existing = ! empty($validated['id']) ? Event::where('club_id', $club->id)->find($validated['id']) : null;
            $visibility = Visibility::from($validated['visibility'] ?? $existing?->visibility->value ?? Visibility::Club->value);
            $rsvpAudience = Visibility::from($validated['rsvp_audience'] ?? $existing?->rsvp_audience->value ?? Visibility::Club->value);

            if (! $rsvpAudience->isNoBroaderThan($visibility)) {
                throw ValidationException::withMessages([
                    'rsvp_audience' => 'People who cannot see this event cannot RSVP to it. Choose an RSVP audience that is no wider than who can see the event.',
                ]);
            }

            if (! empty($validated['allow_public_registration']) && $visibility !== Visibility::Public) {
                throw ValidationException::withMessages([
                    'allow_public_registration' => 'Outside guests can only register for events that everyone can see. Set "Who can see this event" to Public first.',
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
                    'ends_at' => $validated['ends_at'] ?? null,
                    'capacity' => $validated['capacity'] ?? null,
                    'waitlist_enabled' => $validated['waitlist_enabled'] ?? false,
                    'registration_opens_at' => $validated['registration_opens_at'] ?? null,
                    'rsvp_deadline' => $validated['rsvp_deadline'] ?? null,
                    'allow_public_registration' => $validated['allow_public_registration'] ?? false,
                    'max_guests_per_booking' => $validated['max_guests_per_booking'] ?? null,
                    'cancellation_policy' => $validated['cancellation_policy'] ?? null,
                    'booking_fee_type' => $validated['booking_fee_type'] ?? 'none',
                    'booking_fee_amount' => ($validated['booking_fee_type'] ?? 'none') === 'none' ? 0 : ($validated['booking_fee_amount'] ?? 0),
                    'booking_fee_scope' => $validated['booking_fee_scope'] ?? 'per_booking',
                    'booking_fee_label' => $validated['booking_fee_label'] ?? null,
                    'price_display' => $validated['price_display'] ?? 'standard',
                ]
            );

            $justPublished = $event->status === 'upcoming' && ($event->wasRecentlyCreated || $existing?->status === 'draft');

            if ($justPublished) {
                app(ClubNotifier::class)->toMembers($club, ClubNotification::event($event, $club), $request->user());
            }

            if (isset($validated['ticket_tiers'])) {
                $this->syncTiers($event, $request->input('ticket_tiers', []));
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

            if (isset($validated['menu_items']) && $event->has_dining) {
                $this->syncMenu($event, $request->input('menu_items', []));
            }

            if (array_key_exists('payment_methods', $validated)) {
                $this->syncPaymentMethods($event, $club, $request->input('payment_methods', []), $pricing);
            }

            // Someone waiting may now fit if the capacity was raised.
            app(EventRegistrationService::class)->promoteFromWaitlist($event);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
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

    /**
     * Update ticket types in place so the people who chose them keep their choice.
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    private function syncTiers(Event $event, array $incoming): void
    {
        $existing = $event->ticketTiers()->get()->keyBy('id');
        $keep = [];

        foreach ($incoming as $tier) {
            if (empty($tier['name'])) {
                continue;
            }

            $fields = [
                'name' => $tier['name'],
                'price' => $tier['price'] ?? 0,
                'max_quantity' => $tier['max_quantity'] ?? 0,
                'audience' => $tier['audience'] ?? 'all',
            ];

            if (! empty($tier['id']) && $existing->has((int) $tier['id'])) {
                $existing[(int) $tier['id']]->update($fields);
                $keep[] = (int) $tier['id'];
            } else {
                $keep[] = EventTicketTier::create($fields + ['event_id' => $event->id, 'sold_quantity' => 0])->id;
            }
        }

        $removed = $existing->keys()->diff($keep);
        $inUse = EventAttendee::whereIn('ticket_tier_id', $removed)->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['ticket_tiers' => 'A ticket type that people have already booked cannot be removed. Rename it or set its capacity instead.']);
        }

        EventTicketTier::whereIn('id', $removed)->delete();
    }

    /**
     * Update dishes in place, in the order shown, so bookings keep pointing at the same dish.
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    private function syncMenu(Event $event, array $incoming): void
    {
        $existing = $event->menuItems()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($incoming) as $order => $item) {
            if (empty($item['name'])) {
                continue;
            }

            $fields = [
                'category' => $item['category'] ?? 'main',
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'allergens' => $item['allergens'] ?? null,
                'is_vegetarian' => (bool) ($item['is_vegetarian'] ?? false),
                'is_vegan' => (bool) ($item['is_vegan'] ?? false),
                'is_gf' => (bool) ($item['is_gf'] ?? false),
                'sort_order' => $order,
            ];

            if (! empty($item['id']) && $existing->has((int) $item['id'])) {
                $existing[(int) $item['id']]->update($fields);
                $keep[] = (int) $item['id'];
            } else {
                $keep[] = EventMenuItem::create($fields + ['event_id' => $event->id])->id;
            }
        }

        $removed = $existing->keys()->diff($keep);

        if ($removed->isNotEmpty() && collect($this->dishesInUse($event))->intersect($removed)->isNotEmpty()) {
            throw ValidationException::withMessages(['menu_items' => 'A dish that guests have already chosen cannot be removed. Change its name or description instead.']);
        }

        EventMenuItem::whereIn('id', $removed)->delete();
    }

    /**
     * Copy an event as a new draft, without its bookings.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->with(['ticketTiers', 'promos', 'menuItems'])->findOrFail($id);

        $copy = DB::transaction(function () use ($event) {
            $copy = $event->replicate(['slug']);
            $copy->title = 'Copy of '.$event->title;
            $copy->slug = Str::slug($copy->title).'-'.Str::lower(Str::random(5));
            $copy->status = 'draft';
            $copy->save();

            foreach ($event->ticketTiers as $tier) {
                $copy->ticketTiers()->create($tier->only(['name', 'price', 'max_quantity', 'audience']) + ['sold_quantity' => 0]);
            }

            foreach ($event->promos as $promo) {
                $copy->promos()->create($promo->only(['code', 'discount_type', 'discount_amount', 'max_uses']) + ['uses_count' => 0]);
            }

            foreach ($event->menuItems as $item) {
                $copy->menuItems()->create($item->only(['category', 'name', 'description', 'is_vegetarian', 'is_vegan', 'is_gf', 'allergens', 'sort_order']));
            }

            return $copy;
        });

        return redirect()->route('admin.events.edit', ['clubSlug' => $club->slug, 'id' => $copy->id])
            ->with('success', 'Event copied as a draft. Check the date and details, then publish it.');
    }

    /**
     * Cancel an event. Bookings are kept so people can be told.
     */
    public function cancel(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $event->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Event cancelled. Existing bookings have been kept.');
    }

    /**
     * Add a booking by hand.
     */
    public function addRegistration(Request $request, string $clubSlug, int $id, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:255',
            'dietary_requirements' => 'nullable|string|max:1000',
        ]);

        $registrations->addManual($event, $validated['name'], $validated['email'] ?? null, $validated['dietary_requirements'] ?? null);

        return redirect()->back()->with('success', $validated['name'].' added.');
    }

    /**
     * Cancel someone's booking; the waiting list moves up.
     */
    public function cancelRegistration(string $clubSlug, int $id, int $registrationId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $registrations->cancel(EventRegistration::where('event_id', $event->id)->findOrFail($registrationId));

        return redirect()->back()->with('success', 'Booking cancelled.');
    }

    /**
     * Move a waiting booking onto the list now.
     */
    public function promoteRegistration(string $clubSlug, int $id, int $registrationId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $registrations->promote(EventRegistration::where('event_id', $event->id)->where('status', 'waitlisted')->findOrFail($registrationId));

        return redirect()->back()->with('success', 'Moved off the waiting list.');
    }

    /**
     * Switch payment options on for this event, each with its own discount or fee if set.
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    private function syncPaymentMethods(Event $event, Club $club, array $incoming, EventPricing $pricing): void
    {
        $methods = ClubPaymentMethod::where('club_id', $club->id)->get()->keyBy('id');
        $keep = [];

        foreach ($incoming as $row) {
            $method = $methods->get((int) ($row['payment_method_id'] ?? 0));

            if (! $method) {
                throw ValidationException::withMessages(['payment_methods' => 'One of the payment options is not available to this club.']);
            }

            $kind = $row['adjustment_kind'] ?? null;
            $pricing->assertAllowed($method, ['kind' => $kind], 'payment_methods');

            $event->paymentMethods()->updateOrCreate(['payment_method_id' => $method->id], [
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                'adjustment_kind' => $kind,
                'adjustment_mode' => $kind ? ($row['adjustment_mode'] ?? 'fixed') : null,
                'adjustment_amount' => $kind && $kind !== 'none' ? ($row['adjustment_amount'] ?? 0) : null,
                'adjustment_scope' => $kind ? ($row['adjustment_scope'] ?? 'per_person') : null,
                'due_days' => $row['due_days'] ?? null,
                'due_basis' => $row['due_basis'] ?? null,
            ]);

            $keep[] = $method->id;
        }

        $event->paymentMethods()->whereNotIn('payment_method_id', $keep)->delete();
    }

    /**
     * What each enabled option costs for a few typical bookings, worked out by the same engine that charges people.
     *
     * @return array{advertised: array<string, mixed>, scenarios: list<array{label: string, options: list<array<string, mixed>>}>}
     */
    private function pricePreview(Event $event, EventPricing $pricing): array
    {
        $member = ['is_guest' => false, 'attending_dining' => false, 'ticket_tier_id' => $event->ticketTiers()->whereIn('audience', ['all', 'member'])->orderBy('price')->value('id')];
        $guest = ['is_guest' => true, 'attending_dining' => false, 'ticket_tier_id' => $event->ticketTiers()->whereIn('audience', ['all', 'guest'])->orderBy('price')->value('id')];

        $scenarios = [['label' => '1 member', 'people' => [$member]]];

        if ($event->has_dining) {
            $scenarios[] = ['label' => '1 member with dinner', 'people' => [[...$member, 'attending_dining' => true]]];
        }

        $scenarios[] = ['label' => '1 member + 1 guest', 'people' => [$member, $guest]];

        return [
            'advertised' => $pricing->advertised($event),
            'scenarios' => array_map(fn (array $scenario) => [
                'label' => $scenario['label'],
                'options' => array_map(fn (array $row) => ['label' => $row['label'], 'type' => $row['type'], 'total' => $row['total'], 'saving' => $row['saving']], $pricing->priceTable($event, $scenario['people'])),
            ], $scenarios),
        ];
    }
}
