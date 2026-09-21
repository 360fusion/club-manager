<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventPromo;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private User $member;

    private EventRegistrationService $service;

    /** @var array<string, EventPaymentMethod> */
    private array $methods = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.online_payments' => true]);

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->member = User::factory()->create(['name' => 'Mia Member']);
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(30)->startOfHour(), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 50, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 2, 'booking_fee_label' => 'Admin fee']);
        $this->service = app(EventRegistrationService::class);

        $this->enable('online', 'card_online', ['default_adjustment_kind' => 'discount', 'default_adjustment_amount' => 3]);
        $this->enable('bank', 'bank_transfer', ['config' => ['reference_prefix' => 'GALA', 'sort_code' => '20-00-00', 'account_number' => '12345678']]);
        $this->enable('later', 'pay_later', ['default_adjustment_kind' => 'fee', 'default_adjustment_amount' => 2, 'default_adjustment_scope' => 'per_booking', 'due_days' => 7, 'due_basis' => 'before_event']);
    }

    private function enable(string $key, string $type, array $extra = []): void
    {
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => $type, 'label' => ucfirst($key), 'sort_order' => count($this->methods)] + $extra);
        $this->methods[$key] = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);
    }

    private function book(array $extra = [], ?User $user = null)
    {
        return $this->service->register($this->event, $user ?? $this->member, $extra + ['attendees' => [['name' => 'Mia Member'], ['name' => 'Gary', 'is_guest' => true]]]);
    }

    public function test_the_price_is_worked_out_on_the_server_and_stored_with_the_method_chosen(): void
    {
        $registration = $this->book(['payment_method' => $this->methods['online']->id]);

        $this->assertSame('100.00', $registration->subtotal);
        $this->assertSame('-6.00', $registration->method_adjustment, '3 off each for two people');
        $this->assertSame('2.00', $registration->booking_fee);
        $this->assertSame('96.00', $registration->total);
        $this->assertSame('unpaid', $registration->payment_status);
        $this->assertSame($this->methods['online']->payment_method_id, $registration->payment_method_id);
        $this->assertSame(['50.00', '50.00'], $registration->attendees->pluck('price')->all());
        $this->assertNull($registration->due_at);
    }

    public function test_a_choice_of_payment_method_is_required_and_must_be_one_the_event_offers(): void
    {
        try {
            $this->book();
            $this->fail('A payment choice is needed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
        }

        $this->methods['bank']->update(['is_enabled' => false]);

        try {
            $this->book(['payment_method' => $this->methods['bank']->id]);
            $this->fail('A switched-off method is not available.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
        }

        $this->assertSame(0, $this->event->registrations()->count());
    }

    public function test_bank_transfers_get_a_unique_reference_with_the_lodges_prefix_and_pay_later_gets_a_due_date(): void
    {
        $bank = $this->book(['payment_method' => $this->methods['bank']->id]);
        $this->assertSame(sprintf('GALA-%d-%05d', $this->event->id, $bank->id), $bank->payment_reference);
        $this->assertSame('102.00', $bank->total);

        $other = User::factory()->create();
        $this->club->users()->attach($other->id, ['role' => 'member', 'status' => 'active']);
        $later = $this->book(['payment_method' => $this->methods['later']->id, 'attendees' => [['name' => 'Other']]], $other);

        $this->assertNotSame($bank->payment_reference, $later->payment_reference);
        $this->assertSame('54.00', $later->total, '50 + 2 later fee + 2 admin fee');
        $this->assertEquals($this->event->starts_at->copy()->subDays(7)->toDateTimeString(), $later->due_at->toDateTimeString());
    }

    public function test_promo_codes_are_counted_once_per_booking_even_when_it_is_edited(): void
    {
        EventPromo::create(['event_id' => $this->event->id, 'code' => 'EARLY10', 'discount_type' => 'percent', 'discount_amount' => 10, 'max_uses' => 1, 'uses_count' => 0]);
        $pay = $this->methods['bank']->id;

        $first = $this->book(['payment_method' => $pay, 'promo_code' => 'early10']);
        $this->assertSame('EARLY10', $first->promo_code);
        $this->assertSame('10.00', $first->promo_discount);
        $this->assertSame(1, EventPromo::sole()->uses_count);

        $again = $this->book(['payment_method' => $pay, 'promo_code' => 'EARLY10']);
        $this->assertSame('10.00', $again->promo_discount, 'the same booking keeps its promo when edited');
        $this->assertSame(1, EventPromo::sole()->uses_count, 'and is not counted twice');

        $other = User::factory()->create();
        $this->club->users()->attach($other->id, ['role' => 'member', 'status' => 'active']);

        $this->expectException(ValidationException::class);
        $this->book(['payment_method' => $pay, 'promo_code' => 'EARLY10', 'attendees' => [['name' => 'Other']]], $other);
    }

    public function test_editing_a_part_paid_booking_recalculates_and_keeps_what_was_paid(): void
    {
        $registration = $this->book(['payment_method' => $this->methods['bank']->id]);
        $registration->update(['amount_paid' => 102, 'payment_status' => 'paid']);

        $smaller = $this->book(['payment_method' => $this->methods['bank']->id, 'attendees' => [['name' => 'Mia Member']]]);
        $this->assertSame('52.00', $smaller->total);
        $this->assertSame('102.00', $smaller->amount_paid);
        $this->assertSame('paid', $smaller->payment_status, 'paid more than the new total');

        $bigger = $this->book(['payment_method' => $this->methods['bank']->id, 'attendees' => [['name' => 'Mia Member'], ['name' => 'A', 'is_guest' => true], ['name' => 'B', 'is_guest' => true]]]);
        $this->assertSame('152.00', $bigger->total);
        $this->assertSame('part_paid', $bigger->payment_status);
        $this->assertSame(50.0, $bigger->balanceDue());
    }

    public function test_free_events_and_events_without_payment_options_need_no_choice(): void
    {
        $this->event->update(['requires_payment' => false]);
        $free = $this->book();
        $this->assertSame('0.00', $free->total);
        $this->assertSame('paid', $free->payment_status, 'nothing is owed');
        $this->assertNull($free->payment_method_id);

        $this->event->update(['requires_payment' => true]);
        $this->event->paymentMethods()->delete();
        $manual = $this->book(['attendees' => [['name' => 'Mia Member']]]);
        $this->assertSame('52.00', $manual->total);
        $this->assertSame('unpaid', $manual->payment_status);
    }

    public function test_the_live_quote_matches_what_the_booking_is_charged(): void
    {
        $people = [['is_guest' => false], ['is_guest' => true]];

        $response = $this->actingAs($this->member)->postJson(route('member.events.quote', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendees' => $people])->assertOk();

        $totals = collect($response->json('options'))->pluck('total', 'label')->all();
        $this->assertEquals(['Online' => 96, 'Bank' => 102, 'Later' => 104], $totals);
        $this->assertEquals(50, $response->json('advertised.headline'), 'standard display shows the ticket price');

        foreach (['online' => 'Online', 'bank' => 'Bank', 'later' => 'Later'] as $key => $label) {
            $booker = User::factory()->create();
            $this->club->users()->attach($booker->id, ['role' => 'member', 'status' => 'active']);
            $booking = $this->book(['payment_method' => $this->methods[$key]->id], $booker);

            $this->assertEquals($totals[$label], (float) $booking->total, "{$label} quote equals the charge");
        }
    }

    public function test_a_bad_promo_code_gets_a_clear_message_and_outsiders_cannot_ask_for_a_quote(): void
    {
        $this->actingAs($this->member)->postJson(route('member.events.quote', ['slug' => 'club-a', 'id' => $this->event->id]), ['promo_code' => 'NOPE', 'attendees' => [['is_guest' => false]]])
            ->assertStatus(422)->assertJsonPath('message', 'That promo code is not valid.');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(route('member.events.quote', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendees' => [['is_guest' => false]]])->assertNotFound();

        $this->postJson(route('public.event.quote', ['clubSlug' => 'club-a', 'eventSlug' => 'dinner']), ['attendees' => [['is_guest' => false]]])->assertNotFound();
    }
}
