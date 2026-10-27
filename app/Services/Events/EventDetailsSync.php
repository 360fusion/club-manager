<?php

namespace App\Services\Events;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventTicketTier;
use Illuminate\Validation\ValidationException;

/**
 * Saving the parts of an event that hang off it: ticket types, promo codes, dishes and payment options. Ticket types and
 * dishes are edited in place (by id), because bookings point at them.
 */
class EventDetailsSync
{
    /**
     * Ids of dishes someone has already chosen.
     *
     * @return list<int>
     */
    public function dishesInUse(Event $event): array
    {
        $ids = $event->menuItems->pluck('id');

        return EventAttendee::query()
            ->where(fn ($q) => $q->whereIn('starter_item_id', $ids)->orWhereIn('main_item_id', $ids)->orWhereIn('dessert_item_id', $ids))
            ->get(['starter_item_id', 'main_item_id', 'dessert_item_id'])
            ->flatMap(fn ($a) => [$a->starter_item_id, $a->main_item_id, $a->dessert_item_id])
            ->filter()->unique()->values()->all();
    }

    /**
     * Update ticket types in place so the people who chose them keep their choice.
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    public function tiers(Event $event, array $incoming): void
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
    public function menu(Event $event, array $incoming): void
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
     * Switch payment options on for this event, each with its own discount or fee if set.
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    public function paymentMethods(Event $event, Club $club, array $incoming, EventPricing $pricing): void
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

        // Options a lodge has switched off are not in the form, so their settings on this event are kept for when they come back.
        $event->paymentMethods()->whereNotIn('payment_method_id', $keep)->whereHas('method', fn ($q) => $q->where('is_active', true))->delete();
    }

    /**
     * Replace the event's promo codes with the ones submitted (blank codes are skipped).
     *
     * @param  list<array<string, mixed>>  $incoming
     */
    public function promos(Event $event, array $incoming): void
    {
        $event->promos()->delete();

        foreach ($incoming as $promo) {
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
}
