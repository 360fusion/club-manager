<?php

namespace App\Services\Events;

use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventPromo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The one place a booking's price is worked out: tickets, dinner, promo code, the chosen payment
 * method's discount or fee, and the booking fee. The screen shows what this returns and the
 * booking stores it; nothing the browser sends is ever trusted as a price.
 *
 * Amounts are whole pence inside, so totals never drift.
 *
 * @phpstan-type Person array{is_guest?: bool, ticket_tier_id?: int|string|null, attending_dining?: bool}
 * @phpstan-type Quote array{people: list<array{ticket: float, dining: float, price: float}>, subtotal: float, promo_code: ?string, promo_discount: float, method_adjustment: float, method_label: ?string, booking_fee: float, booking_fee_label: ?string, total: float}
 */
class EventPricing
{
    /**
     * The price of a booking for the given people.
     *
     * @param  list<Person>  $people
     * @return Quote
     */
    public function quote(Event $event, array $people, ?string $promoCode = null, ?EventPaymentMethod $method = null, bool $promoAlreadyUsed = false): array
    {
        $lines = $this->lines($event, $people);
        $subtotal = array_sum(array_column($lines, 'cents'));
        $persons = count($people);

        $promo = $promoCode !== null && trim($promoCode) !== '' ? $this->promo($event, $promoCode, $promoAlreadyUsed) : null;
        $promoDiscount = $promo ? $this->promoCents($promo, $subtotal) : 0;
        $base = max(0, $subtotal - $promoDiscount);

        // Nothing to discount, add a fee to, or charge a fee on when the booking is free.
        $adjustment = $base > 0 && $method ? $this->adjustmentCents($method, $base, $persons) : 0;
        $bookingFee = $base > 0 ? $this->bookingFeeCents($event, $base, $persons) : 0;

        $total = max(0, $base + $adjustment + $bookingFee);

        return [
            'people' => array_map(fn (array $line) => [
                'ticket' => $line['ticket'] / 100.0,
                'dining' => $line['dining'] / 100.0,
                'price' => $line['cents'] / 100.0,
            ], $lines),
            'subtotal' => $subtotal / 100.0,
            'promo_code' => $promo?->code,
            'promo_discount' => $promoDiscount / 100.0,
            'method_adjustment' => $adjustment / 100.0,
            'method_label' => $method?->method?->label,
            'booking_fee' => $bookingFee / 100.0,
            'booking_fee_label' => $bookingFee > 0 ? ($event->booking_fee_label ?: 'Booking fee') : null,
            'total' => $total / 100.0,
        ];
    }

    /**
     * What each enabled payment method would cost for these people.
     *
     * @param  list<Person>  $people
     * @return list<array{id: int, type: string, label: string, instructions: ?string, total: float, saving: float, quote: Quote}>
     */
    public function priceTable(Event $event, array $people, ?string $promoCode = null): array
    {
        $methods = $this->enabledMethods($event);

        if ($methods->isEmpty()) {
            return [];
        }

        $rows = $methods->map(function (EventPaymentMethod $method) use ($event, $people, $promoCode) {
            $quote = $this->quote($event, $people, $promoCode, $method);

            return ['id' => $method->id, 'type' => $method->method->type, 'label' => $method->method->label, 'instructions' => $method->method->instructions, 'total' => $quote['total'], 'quote' => $quote];
        })->values();

        $highest = $rows->max('total');

        return $rows->map(fn (array $row) => $row + ['saving' => round($highest - $row['total'], 2)])->all();
    }

    /**
     * The price to advertise on lists and the event page, for one member with no dinner.
     *
     * "all_in" shows the highest price any payment option costs (fees included) and treats the
     * cheaper options as savings, so nothing is added late. "standard" shows the ticket price.
     *
     * @return array{headline: float, lowest: float, has_saving: bool, mode: string}
     */
    public function advertised(Event $event): array
    {
        $person = [['is_guest' => false, 'attending_dining' => false, 'ticket_tier_id' => $this->cheapestTierFor($event)]];
        $table = $this->priceTable($event, $person);
        $base = $this->quote($event, $person);

        if ($table === [] || $event->price_display !== 'all_in') {
            return ['headline' => $event->price_display === 'all_in' ? $base['total'] : $base['subtotal'], 'lowest' => $base['total'], 'has_saving' => false, 'mode' => 'standard'];
        }

        $totals = array_column($table, 'total');

        return ['headline' => max($totals), 'lowest' => min($totals), 'has_saving' => max($totals) > min($totals), 'mode' => 'all_in'];
    }

    /**
     * A fee may only be added to Pay later and Pay on the night; card and bank payments can only be discounted.
     *
     * @param  array{kind?: ?string}  $adjustment
     */
    public function assertAllowed(ClubPaymentMethod $method, array $adjustment, string $field = 'adjustment_kind'): void
    {
        if (($adjustment['kind'] ?? 'none') === 'fee' && ! $method->allowsFee()) {
            throw ValidationException::withMessages([$field => "A fee can't be added to {$method->label}. Card and bank payments can only be discounted."]);
        }
    }

    /**
     * @return Collection<int, EventPaymentMethod>
     */
    public function enabledMethods(Event $event): Collection
    {
        return $event->paymentMethods()
            ->where('is_enabled', true)
            ->whereHas('method', fn ($q) => $q->where('is_active', true)->when(! config('events.online_payments'), fn ($q) => $q->where('type', '!=', ClubPaymentMethod::CARD)))
            ->with('method')
            ->get()
            ->sortBy(fn (EventPaymentMethod $m) => $m->method->sort_order)
            ->values();
    }

    /**
     * @param  list<Person>  $people
     * @return list<array{ticket: int, dining: int, cents: int}>
     */
    private function lines(Event $event, array $people): array
    {
        $tiers = $event->ticketTiers()->get()->keyBy('id');
        $charged = (bool) $event->requires_payment;

        return array_map(function (array $person) use ($event, $tiers, $charged) {
            $tier = ! empty($person['ticket_tier_id']) ? $tiers->get((int) $person['ticket_tier_id']) : null;
            $ticket = $charged ? $this->cents($tier ? $tier->price : $event->price) : 0;
            $dining = $charged && $event->has_dining && ! empty($person['attending_dining']) ? $this->cents($event->dining_price) : 0;

            return ['ticket' => $ticket, 'dining' => $dining, 'cents' => $ticket + $dining];
        }, $people);
    }

    private function promo(Event $event, string $code, bool $alreadyUsed = false): EventPromo
    {
        $promo = EventPromo::where('event_id', $event->id)->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $promo || (! $alreadyUsed && $promo->max_uses > 0 && $promo->uses_count >= $promo->max_uses)) {
            throw ValidationException::withMessages(['promo_code' => 'That promo code is not valid.']);
        }

        return $promo;
    }

    private function promoCents(EventPromo $promo, int $subtotal): int
    {
        $off = $promo->discount_type === 'fixed'
            ? $this->cents($promo->discount_amount)
            : (int) round($subtotal * ((float) $promo->discount_amount) / 100.0);

        return min($subtotal, max(0, $off));
    }

    /**
     * Signed pence: negative for a discount, positive for a fee.
     */
    private function adjustmentCents(EventPaymentMethod $method, int $base, int $persons): int
    {
        $adjustment = $method->adjustment();

        if ($adjustment['kind'] === 'none' || $adjustment['amount'] <= 0) {
            return 0;
        }

        $amount = $adjustment['mode'] === 'percent'
            ? (int) round($base * $adjustment['amount'] / 100.0)
            : $this->cents($adjustment['amount']) * ($adjustment['scope'] === 'per_booking' ? 1 : $persons);

        if ($adjustment['kind'] === 'discount') {
            return -min($base, $amount);
        }

        // A fee is only ever charged on the types that allow one.
        return $method->method?->allowsFee() ? $amount : 0;
    }

    private function bookingFeeCents(Event $event, int $base, int $persons): int
    {
        $amount = (float) $event->booking_fee_amount;

        if ($event->booking_fee_type === 'none' || $amount <= 0) {
            return 0;
        }

        if ($event->booking_fee_type === 'percent') {
            return (int) round($base * $amount / 100.0);
        }

        return $this->cents($amount) * ($event->booking_fee_scope === 'per_ticket' ? $persons : 1);
    }

    private function cheapestTierFor(Event $event): ?int
    {
        $tier = $event->ticketTiers()->whereIn('audience', ['all', 'member'])->orderBy('price')->first();

        return $tier?->id;
    }

    private function cents(float|string|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
