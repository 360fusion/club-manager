<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\Visibility;
use App\Http\Requests\StoreEventRequest;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Services\Events\EventDetailsSync;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $club = $this->findClub($clubSlug);
        $events = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'promos', 'menuItems'])
            ->withCount('attendees')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Events/Index', [
            'club' => $club,
            'events' => $events,
        ]);
    }

    /**
     * Show form for creating or editing an event.
     */
    public function edit(string $clubSlug, EventPricing $pricing, EventDetailsSync $details, ?int $id = null): Response
    {
        $club = $this->findClub($clubSlug);

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
            'dishesInUse' => $id ? $details->dishesInUse($event) : [],
            'registrationCount' => $id ? $event->registrations()->whereIn('status', EventRegistration::HOLDING_PLACE)->count() : 0,
            'clubPaymentMethods' => ClubPaymentMethod::where('club_id', $club->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn (ClubPaymentMethod $m) => $m->only(['id', 'type', 'label', 'default_adjustment_kind', 'default_adjustment_mode', 'default_adjustment_amount', 'default_adjustment_scope', 'due_days', 'due_basis']) + ['complete' => $m->hasCompleteDetails()])->values(),
            'enabledMethods' => $id ? $event->paymentMethods()->get(['payment_method_id', 'is_enabled', 'adjustment_kind', 'adjustment_mode', 'adjustment_amount', 'adjustment_scope', 'due_days', 'due_basis']) : [],
            'pricePreview' => $id ? $this->pricePreview($event, $pricing) : null,
        ]);
    }

    /**
     * Store or update an event with ticket tiers, promos, and dining items.
     */
    public function store(StoreEventRequest $request, string $clubSlug, EventPricing $pricing, EventDetailsSync $details): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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

            $justPublished = $event->status === EventStatus::Upcoming->value && ($event->wasRecentlyCreated || $existing?->status === EventStatus::Draft->value);

            if ($justPublished) {
                app(ClubNotifier::class)->toMembers($club, ClubNotification::event($event, $club), $request->user());
            }

            if (isset($validated['ticket_tiers'])) {
                $details->tiers($event, $request->input('ticket_tiers', []));
            }

            if (isset($validated['promos'])) {
                $details->promos($event, $validated['promos']);
            }

            if (isset($validated['menu_items']) && $event->has_dining) {
                $details->menu($event, $request->input('menu_items', []));
            }

            if (array_key_exists('payment_methods', $validated)) {
                $details->paymentMethods($event, $club, $request->input('payment_methods', []), $pricing);
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
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $event->delete();

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }

    /**
     * Copy an event as a new draft, without its bookings.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->with(['ticketTiers', 'promos', 'menuItems', 'paymentMethods'])->findOrFail($id);

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

            foreach ($event->paymentMethods as $option) {
                $copy->paymentMethods()->create($option->only(['payment_method_id', 'is_enabled', 'adjustment_kind', 'adjustment_mode', 'adjustment_amount', 'adjustment_scope', 'due_days', 'due_basis']));
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
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $event->update(['status' => EventStatus::Cancelled->value]);

        return redirect()->back()->with('success', 'Event cancelled. Existing bookings have been kept.');
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
