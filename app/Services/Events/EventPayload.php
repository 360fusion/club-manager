<?php

namespace App\Services\Events;

use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The event shapes sent to the member and public pages, built in one place.
 */
class EventPayload
{
    /**
     * A member's own bookings for these events, keyed by event id.
     *
     * @param  iterable<int>  $eventIds
     * @return Collection<int, EventRegistration>
     */
    public static function registrationsFor(?User $user, iterable $eventIds): Collection
    {
        if (! $user) {
            return collect();
        }

        return EventRegistration::with(['attendees.starter', 'attendees.main', 'attendees.dessert'])
            ->where('user_id', $user->id)
            ->whereIn('event_id', collect($eventIds)->all())
            ->get()
            ->keyBy('event_id');
    }

    /**
     * The event as a member sees it, with their own booking if they have one.
     *
     * @return array<string, mixed>
     */
    public static function forMember(Event $event, ?EventRegistration $registration = null): array
    {
        $cutoff = $event->booking_cutoff_at;
        $event->loadMissing(['menuItems', 'ticketTiers', 'club']);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'status' => $event->status,
            'description' => $event->description,
            'location' => $event->formatted_location ?: $event->location,
            'address_line_1' => $event->address_line_1,
            'address_line_2' => $event->address_line_2,
            'city' => $event->city,
            'county' => $event->county,
            'postcode' => $event->postcode,
            'starts_at' => $event->starts_at?->format('M d, Y @ H:i'),
            'ends_at' => $event->ends_at?->format('M d, Y @ H:i'),
            'booking_cutoff_days' => $event->booking_cutoff_days,
            'booking_cutoff_at' => $cutoff?->format('M d, Y @ H:i'),
            'is_booking_closed' => $event->is_booking_closed,
            'has_dining' => $event->has_dining,
            'dining_price' => number_format((float) $event->dining_price, 2),
            'price' => number_format((float) $event->price, 2),
            'capacity' => $event->capacity,
            'places_left' => $event->placesLeft(),
            'waitlist_enabled' => $event->waitlist_enabled,
            'max_guests' => $event->max_guests_per_booking ?? $event->club->maxGuestsPerMember(),
            'cancellation_policy' => $event->cancellation_policy,
            'requires_payment' => (bool) $event->requires_payment,
            'booking_fee_label' => $event->booking_fee_type !== 'none' ? ($event->booking_fee_label ?: 'Booking fee') : null,
            'advertised' => $event->requires_payment ? app(EventPricing::class)->advertised($event) : null,
            'payment_options' => app(EventPricing::class)->enabledMethods($event)->map(fn ($option) => [
                'id' => $option->id,
                'type' => $option->method->type,
                'label' => $option->method->label,
                'instructions' => $option->method->instructions,
            ])->values(),
            'menu' => self::menu($event),
            // Still sent flat for pages written before dishes were grouped by course.
            'menu_items' => $event->menuItems,
            'ticket_tiers' => $event->ticketTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'name' => $tier->name,
                'price' => number_format((float) $tier->price, 2),
                'audience' => $tier->audience ?: 'all',
            ])->values(),
            'user_rsvp' => $registration ? self::registration($registration) : null,
        ];
    }

    /**
     * The dishes grouped by course, in the order the organiser set.
     *
     * @return array{starter: list<array<string, mixed>>, main: list<array<string, mixed>>, dessert: list<array<string, mixed>>}
     */
    public static function menu(Event $event): array
    {
        $grouped = ['starter' => [], 'main' => [], 'dessert' => []];

        foreach ($event->menuItems->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $item) {
            if (isset($grouped[$item->category])) {
                $grouped[$item->category][] = self::dish($item);
            }
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dish(EventMenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'is_vegetarian' => (bool) $item->is_vegetarian,
            'is_vegan' => (bool) $item->is_vegan,
            'is_gf' => (bool) $item->is_gf,
            'allergens' => $item->allergens,
        ];
    }

    /**
     * A booking and its people. The top-level meal fields describe the booker, for
     * pages that predate guests.
     *
     * @return array<string, mixed>
     */
    public static function registration(EventRegistration $registration): array
    {
        $registration->loadMissing(['attendees.starter', 'attendees.main', 'attendees.dessert']);
        $booker = $registration->booker();

        return [
            'id' => $registration->id,
            'attendance_status' => $registration->status,
            'payment_status' => $registration->payment_status,
            'amount_paid' => $registration->amount_paid,
            'total' => $registration->total,
            'payment' => self::payment($registration),
            'checked_in_at' => $booker?->checked_in_at?->toIso8601String(),
            'attendees' => $registration->attendees->map(fn (EventAttendee $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'is_guest' => $a->is_guest,
                'ticket_tier_id' => $a->ticket_tier_id,
                'attending_dining' => $a->attending_dining,
                'starter_item_id' => $a->starter_item_id,
                'main_item_id' => $a->main_item_id,
                'dessert_item_id' => $a->dessert_item_id,
                'dietary_requirements' => $a->dietary_requirements,
                'meal' => $a->mealSummary(),
            ])->values(),
            'attending_dining' => (bool) $booker?->attending_dining,
            'menu_selections' => $booker?->mealSummary() ?? [],
            'dietary_requirements' => $booker?->dietary_requirements,
        ];
    }

    /**
     * What someone needs to know about paying: the amount, what has been paid, and how to pay the rest.
     *
     * @return array<string, mixed>
     */
    public static function payment(EventRegistration $registration): array
    {
        $registration->loadMissing(['paymentMethod', 'event']);
        $method = $registration->paymentMethod;
        $bankMethod = $method?->type === ClubPaymentMethod::BANK
            ? $method
            : ($method?->type === ClubPaymentMethod::LATER
                ? ClubPaymentMethod::where('club_id', $registration->event->club_id)->where('type', ClubPaymentMethod::BANK)->where('is_active', true)->first()
                : null);

        return [
            'status' => $registration->payment_status,
            'total' => $registration->total,
            'amount_paid' => $registration->amount_paid,
            'balance' => number_format($registration->balanceDue(), 2, '.', ''),
            'subtotal' => $registration->subtotal,
            'promo_code' => $registration->promo_code,
            'promo_discount' => $registration->promo_discount,
            'method_adjustment' => $registration->method_adjustment,
            'method_label' => $method?->label,
            'method_type' => $method?->type,
            'booking_fee' => $registration->booking_fee,
            'booking_fee_label' => (float) $registration->booking_fee > 0 ? ($registration->event->booking_fee_label ?: 'Booking fee') : null,
            'reference' => $registration->payment_reference,
            'due_at' => $registration->due_at?->format('j M Y'),
            'instructions' => $method?->instructions,
            'bank' => $bankMethod ? $bankMethod->bankDetails() : null,
            'selected_option_id' => $method ? $registration->event->paymentMethods()->where('payment_method_id', $method->id)->value('id') : null,
        ];
    }
}
