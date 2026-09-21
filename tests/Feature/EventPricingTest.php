<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventPromo;
use App\Models\EventTicketTier;
use App\Services\Events\EventPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventPricingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private EventPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.online_payments' => true]);

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming', 'requires_payment' => true, 'price' => 50, 'has_dining' => true, 'dining_price' => 20]);
        $this->pricing = app(EventPricing::class);
    }

    private function method(string $type, string $label, array $adjustment = [], bool $enable = true, ?array $override = null): EventPaymentMethod
    {
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => $type, 'label' => $label, 'sort_order' => ClubPaymentMethod::count()] + ($type === 'card_online' ? ['config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => 'whsec_x']] : []) + array_filter([
            'default_adjustment_kind' => $adjustment['kind'] ?? null, 'default_adjustment_mode' => $adjustment['mode'] ?? null,
            'default_adjustment_amount' => $adjustment['amount'] ?? null, 'default_adjustment_scope' => $adjustment['scope'] ?? null,
        ], fn ($v) => $v !== null));

        return EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => $enable] + ($override ?? []))->load('method');
    }

    private function person(bool $dining = false, ?int $tier = null, bool $guest = false): array
    {
        return ['is_guest' => $guest, 'attending_dining' => $dining, 'ticket_tier_id' => $tier];
    }

    public function test_tickets_and_dinner_are_charged_per_person(): void
    {
        $quote = $this->pricing->quote($this->event, [$this->person(true), $this->person(false), $this->person(true, null, true)]);

        $this->assertSame(190.0, $quote['subtotal']);
        $this->assertSame(190.0, $quote['total']);
        $this->assertSame([70.0, 50.0, 70.0], array_column($quote['people'], 'price'));
    }

    public function test_ticket_types_replace_the_event_price_and_a_free_event_charges_nothing(): void
    {
        $member = EventTicketTier::create(['event_id' => $this->event->id, 'name' => 'Member', 'price' => 30, 'audience' => 'member']);

        $this->assertSame(30.0, $this->pricing->quote($this->event, [$this->person(false, $member->id)])['total']);

        $this->event->update(['requires_payment' => false, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 3]);
        $free = $this->pricing->quote($this->event->fresh(), [$this->person(true)]);

        $this->assertSame(0.0, $free['total']);
        $this->assertSame(0.0, $free['booking_fee'], 'no fee on a free booking');
    }

    public function test_an_online_discount_and_a_pay_later_fee_apply_per_person_or_per_booking(): void
    {
        $online = $this->method('card_online', 'Pay online', ['kind' => 'discount', 'mode' => 'fixed', 'amount' => 3, 'scope' => 'per_person']);
        $bank = $this->method('bank_transfer', 'Bank transfer');
        $later = $this->method('pay_later', 'Pay later', ['kind' => 'fee', 'mode' => 'fixed', 'amount' => 2, 'scope' => 'per_booking']);
        $door = $this->method('cash_on_door', 'On the night', ['kind' => 'fee', 'mode' => 'fixed', 'amount' => 5, 'scope' => 'per_person']);
        $two = [$this->person(), $this->person(false, null, true)];

        $totals = array_column($this->pricing->priceTable($this->event, $two), 'total', 'label');

        $this->assertSame(['Pay online' => 94.0, 'Bank transfer' => 100.0, 'Pay later' => 102.0, 'On the night' => 110.0], $totals);

        $saving = array_column($this->pricing->priceTable($this->event, $two), 'saving', 'label');
        $this->assertSame(['Pay online' => 16.0, 'Bank transfer' => 10.0, 'Pay later' => 8.0, 'On the night' => 0.0], $saving);
    }

    public function test_percent_adjustments_and_an_event_override_of_the_default(): void
    {
        $online = $this->method('card_online', 'Pay online', ['kind' => 'discount', 'mode' => 'percent', 'amount' => 10]);

        $this->assertSame(45.0, $this->pricing->quote($this->event, [$this->person()], null, $online)['total']);

        $online->update(['adjustment_kind' => 'discount', 'adjustment_mode' => 'fixed', 'adjustment_amount' => 1.5, 'adjustment_scope' => 'per_person']);
        $this->assertSame(48.5, $this->pricing->quote($this->event, [$this->person()], null, $online->fresh('method'))['total']);

        $online->update(['adjustment_kind' => 'none']);
        $this->assertSame(50.0, $this->pricing->quote($this->event, [$this->person()], null, $online->fresh('method'))['total']);
    }

    public function test_a_discount_can_never_take_the_price_below_zero(): void
    {
        $online = $this->method('card_online', 'Pay online', ['kind' => 'discount', 'mode' => 'fixed', 'amount' => 80, 'scope' => 'per_person']);

        $this->assertSame(0.0, $this->pricing->quote($this->event, [$this->person()], null, $online)['total']);
    }

    public function test_fees_are_ignored_on_card_and_bank_and_rejected_when_saving_them(): void
    {
        $online = $this->method('card_online', 'Pay online', ['kind' => 'fee', 'mode' => 'fixed', 'amount' => 4]);

        $this->assertSame(50.0, $this->pricing->quote($this->event, [$this->person()], null, $online)['total'], 'even if a fee slipped into the data it is never charged');

        $this->expectException(ValidationException::class);
        $this->pricing->assertAllowed($online->method, ['kind' => 'fee']);
    }

    public function test_the_booking_fee_is_the_same_for_every_method_and_can_be_per_booking_per_ticket_or_percent(): void
    {
        $bank = $this->method('bank_transfer', 'Bank transfer');
        $two = [$this->person(), $this->person(false, null, true)];

        $this->event->update(['booking_fee_type' => 'fixed', 'booking_fee_amount' => 2.5, 'booking_fee_scope' => 'per_booking', 'booking_fee_label' => 'Admin fee']);
        $quote = $this->pricing->quote($this->event->fresh(), $two, null, $bank);
        $this->assertSame(102.5, $quote['total']);
        $this->assertSame('Admin fee', $quote['booking_fee_label']);

        $this->event->update(['booking_fee_scope' => 'per_ticket']);
        $this->assertSame(105.0, $this->pricing->quote($this->event->fresh(), $two, null, $bank)['total']);

        $this->event->update(['booking_fee_type' => 'percent', 'booking_fee_amount' => 5]);
        $this->assertSame(105.0, $this->pricing->quote($this->event->fresh(), $two, null, $bank)['total'], '5% of 100');
    }

    public function test_promo_codes_reduce_the_subtotal_and_stop_at_their_limit(): void
    {
        EventPromo::create(['event_id' => $this->event->id, 'code' => 'EARLY10', 'discount_type' => 'percent', 'discount_amount' => 10, 'max_uses' => 1, 'uses_count' => 0]);
        EventPromo::create(['event_id' => $this->event->id, 'code' => 'FIVEOFF', 'discount_type' => 'fixed', 'discount_amount' => 5, 'max_uses' => 0, 'uses_count' => 99]);

        $percent = $this->pricing->quote($this->event, [$this->person()], ' early10 ');
        $this->assertSame(5.0, $percent['promo_discount']);
        $this->assertSame(45.0, $percent['total']);
        $this->assertSame(45.0, $this->pricing->quote($this->event, [$this->person()], 'FIVEOFF')['total'], 'max_uses 0 means unlimited');

        EventPromo::where('code', 'EARLY10')->update(['uses_count' => 1]);
        $this->expectException(ValidationException::class);
        $this->pricing->quote($this->event, [$this->person()], 'EARLY10');
    }

    public function test_the_advertised_price_absorbs_the_highest_fee_or_shows_the_standard_price(): void
    {
        $this->method('card_online', 'Pay online', ['kind' => 'discount', 'mode' => 'fixed', 'amount' => 3, 'scope' => 'per_person']);
        $this->method('cash_on_door', 'On the night', ['kind' => 'fee', 'mode' => 'fixed', 'amount' => 5, 'scope' => 'per_person']);

        $standard = $this->pricing->advertised($this->event);
        $this->assertSame(['headline' => 50.0, 'lowest' => 50.0, 'has_saving' => false, 'mode' => 'standard'], $standard);

        $this->event->update(['price_display' => 'all_in']);
        $allIn = $this->pricing->advertised($this->event->fresh());
        $this->assertSame(55.0, $allIn['headline']);
        $this->assertSame(47.0, $allIn['lowest']);
        $this->assertTrue($allIn['has_saving']);
    }

    public function test_disabled_and_inactive_methods_are_not_offered(): void
    {
        $this->method('bank_transfer', 'Bank transfer');
        $this->method('card_online', 'Pay online', [], false);
        $inactive = $this->method('pay_later', 'Pay later');
        $inactive->method->update(['is_active' => false]);

        $this->assertSame(['Bank transfer'], array_column($this->pricing->priceTable($this->event, [$this->person()]), 'label'));
    }

    public function test_pence_do_not_drift(): void
    {
        $this->event->update(['price' => 19.99]);
        $online = $this->method('card_online', 'Pay online', ['kind' => 'discount', 'mode' => 'percent', 'amount' => 7.5]);

        $quote = $this->pricing->quote($this->event->fresh(), array_fill(0, 3, $this->person()), null, $online);

        $this->assertSame(59.97, $quote['subtotal']);
        $this->assertSame(-4.50, $quote['method_adjustment']);
        $this->assertSame(55.47, $quote['total']);
    }
}
