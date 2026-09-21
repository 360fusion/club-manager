<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentLog;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventPayload;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use App\Services\Payment\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class EventPayPalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private User $member;

    private EventPaymentMethod $paypal;

    private EventPaymentMethod $card;

    private EventPaymentMethod $later;

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.online_payments' => true]);
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(20), 'status' => 'upcoming', 'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Public, 'requires_payment' => true, 'price' => 50]);

        $card = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'card_online', 'label' => 'Card', 'sort_order' => 0, 'default_adjustment_kind' => 'discount', 'default_adjustment_amount' => 3, 'config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => 'whsec_x']]);
        $paypal = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'paypal', 'label' => 'PayPal', 'sort_order' => 1, 'default_adjustment_kind' => 'discount', 'default_adjustment_amount' => 2, 'config' => ['paypal_client_id' => 'cid', 'paypal_client_secret' => 'sec', 'paypal_webhook_id' => 'WH-1', 'paypal_mode' => 'sandbox']]);
        $later = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'pay_later', 'label' => 'Pay later', 'sort_order' => 2, 'due_days' => 7]);
        $this->card = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $card->id, 'is_enabled' => true]);
        $this->paypal = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $paypal->id, 'is_enabled' => true]);
        $this->later = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $later->id, 'is_enabled' => true]);

        $this->member = User::factory()->create();
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
    }

    private function book(): EventRegistration
    {
        return app(EventRegistrationService::class)->register($this->event, $this->member, ['payment_method' => $this->later->id, 'attendees' => [['name' => 'Mia'], ['name' => 'Gary', 'is_guest' => true]]]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function capture(EventRegistration $registration, array $overrides = []): array
    {
        return array_merge(['status' => 'COMPLETED', 'capture_id' => 'CAP-1', 'amount' => 96.0, 'currency' => 'GBP', 'custom_id' => (string) $registration->id], $overrides);
    }

    private function startPayPal(EventRegistration $registration): void
    {
        $this->mock(PayPalGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('createOrder')->once()->andReturn(['id' => 'ORDER-1', 'url' => 'https://paypal.test/approve/ORDER-1']);
        });

        $this->actingAs($this->member)->post(route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]), ['option' => $this->paypal->id], ['X-Inertia' => 'true'])->assertStatus(409);
    }

    public function test_a_booking_can_be_paid_by_card_or_paypal_and_paypal_gets_the_online_price_from_the_server(): void
    {
        $registration = $this->book();
        $this->assertSame('100.00', $registration->total);

        $offers = EventPayload::payment($registration->fresh())['online']['options'];
        $this->assertSame(['card_online', 'paypal'], array_column($offers, 'type'));

        $captured = null;
        $this->mock(PayPalGateway::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('createOrder')->once()->andReturnUsing(function ($method, $order) use (&$captured) {
                $captured = $order;

                return ['id' => 'ORDER-1', 'url' => 'https://paypal.test/approve/ORDER-1'];
            });
        });

        $this->actingAs($this->member)->post(route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]), ['option' => $this->paypal->id], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://paypal.test/approve/ORDER-1');

        $registration = $registration->fresh();
        $this->assertSame('96.00', $registration->total, 'two people, 2 off each');
        $this->assertSame($this->paypal->payment_method_id, $registration->payment_method_id);
        $this->assertSame('ORDER-1', $registration->paypal_order_id);
        $this->assertSame(96.0, $captured['amount']);
        $this->assertSame('GBP', $captured['currency']);
        $this->assertSame((string) $registration->id, $captured['custom_id']);
        $this->assertSame(route('member.events.paypal_return', ['slug' => 'club-a', 'id' => $this->event->id]), $captured['return_url']);
        $this->assertStringContainsString('payment=cancelled', $captured['cancel_url']);
    }

    public function test_coming_back_from_paypal_takes_the_payment_once_and_only_for_the_order_this_booking_started(): void
    {
        $registration = $this->book();
        $this->startPayPal($registration);
        $registration = $registration->fresh();
        $url = route('member.events.paypal_return', ['slug' => 'club-a', 'id' => $this->event->id]);

        $this->mock(PayPalGateway::class, function (MockInterface $mock) use ($registration) {
            $mock->shouldReceive('captureOrder')->once()->with(\Mockery::any(), 'ORDER-1')->andReturn($this->capture($registration));
        });

        $this->actingAs($this->member)->get($url.'?token=OTHER-ORDER')->assertRedirect(route('member.events', ['slug' => 'club-a']).'?payment=cancelled');
        $this->assertSame('unpaid', $registration->fresh()->payment_status);

        $this->actingAs($this->member)->get($url.'?token=ORDER-1')->assertRedirect(route('member.events', ['slug' => 'club-a']).'?payment=success');
        $registration = $registration->fresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('96.00', $registration->amount_paid);
        $this->assertSame('CAP-1', $registration->paypal_capture_id);
        $this->assertDatabaseHas('event_payment_log', ['registration_id' => $registration->id, 'action' => 'paypal_paid', 'external_id' => 'CAP-1', 'source' => 'paypal']);

        // A second visit to the same address does not take or record anything again.
        $this->actingAs($this->member)->get($url.'?token=ORDER-1')->assertRedirect(route('member.events', ['slug' => 'club-a']).'?payment=success');
        $this->assertSame(1, EventPaymentLog::where('registration_id', $registration->id)->count());
    }

    public function test_a_capture_for_the_wrong_amount_currency_or_booking_is_not_recorded(): void
    {
        $registration = $this->book();
        $this->startPayPal($registration);
        $registration = $registration->fresh();
        $url = route('member.events.paypal_return', ['slug' => 'club-a', 'id' => $this->event->id]).'?token=ORDER-1';

        foreach ([['amount' => 500.0], ['currency' => 'USD'], ['custom_id' => '999999'], ['status' => 'PENDING']] as $bad) {
            $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('captureOrder')->once()->andReturn($this->capture($registration, $bad)));
            $this->actingAs($this->member)->get($url)->assertRedirect(route('member.events', ['slug' => 'club-a']).'?payment=cancelled');
        }

        $this->assertSame('unpaid', $registration->fresh()->payment_status);
        $this->assertSame(0, EventPaymentLog::count());
    }

    private function webhook(EventRegistration $registration, array $resource = [], string $type = 'PAYMENT.CAPTURE.COMPLETED', ?int $clubId = null)
    {
        $payload = json_encode(['event_type' => $type, 'resource' => array_merge([
            'id' => 'CAP-9', 'status' => 'COMPLETED', 'custom_id' => (string) $registration->id,
            'amount' => ['value' => '96.00', 'currency_code' => 'GBP'],
            'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']],
        ], $resource)]);

        return $this->call('POST', route('webhooks.paypal.club', ['clubId' => $clubId ?? $this->club->id]), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1'], $payload);
    }

    public function test_the_paypal_webhook_records_a_verified_payment_once_and_refuses_anything_else(): void
    {
        $registration = $this->book();
        $this->startPayPal($registration);
        $registration = $registration->fresh();

        $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('verifyWebhook')->andReturn(false));
        $this->webhook($registration)->assertStatus(400);
        $this->assertSame('unpaid', $registration->fresh()->payment_status);

        $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('verifyWebhook')->andReturn(true));
        $this->webhook($registration)->assertOk()->assertJson(['note' => null]);
        $this->assertSame('paid', $registration->fresh()->payment_status);

        $this->webhook($registration)->assertOk()->assertJson(['note' => 'already recorded']);
        $this->assertSame(1, EventPaymentLog::where('registration_id', $registration->id)->count());

        $this->webhook($registration, [], 'CHECKOUT.ORDER.APPROVED')->assertOk()->assertJson(['note' => 'ignored event type']);
        $this->webhook($registration, [], 'PAYMENT.CAPTURE.COMPLETED', 999999)->assertStatus(400);
    }

    public function test_a_webhook_for_another_order_or_lodge_does_nothing(): void
    {
        $registration = $this->book();
        $this->startPayPal($registration);
        $registration = $registration->fresh();
        $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('verifyWebhook')->andReturn(true));

        $this->webhook($registration, ['supplementary_data' => ['related_ids' => ['order_id' => 'FORGED']]])->assertJson(['note' => 'no matching booking']);
        $this->webhook($registration, ['amount' => ['value' => '500.00', 'currency_code' => 'GBP']])->assertJson(['note' => 'amount mismatch']);
        $this->assertSame('unpaid', $registration->fresh()->payment_status);
    }

    public function test_paypal_cannot_carry_a_fee_and_is_hidden_until_set_up_and_switched_on(): void
    {
        $pricing = app(EventPricing::class);

        try {
            $pricing->assertAllowed($this->paypal->method, ['kind' => 'fee']);
            $this->fail('A fee on PayPal should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('adjustment_kind', $e->errors());
        }

        $types = fn () => $pricing->enabledMethods($this->event->fresh())->map->method->pluck('type')->all();
        $this->assertContains('paypal', $types());

        $this->paypal->method->update(['config' => ['paypal_client_id' => 'cid', 'paypal_client_secret' => 'sec']]);
        $this->assertNotContains('paypal', $types(), 'no webhook id, so payments could not be confirmed');

        $this->paypal->method->update(['config' => ['paypal_client_id' => 'cid', 'paypal_client_secret' => 'sec', 'paypal_webhook_id' => 'WH-1']]);
        config(['events.online_payments' => false]);
        $this->assertNotContains('paypal', $types());
    }

    public function test_refunding_a_paypal_payment_goes_back_through_paypal_first(): void
    {
        $registration = $this->book();
        $this->startPayPal($registration);
        $registration = $registration->fresh();
        $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('captureOrder')->andReturn($this->capture($registration)));
        $this->actingAs($this->member)->get(route('member.events.paypal_return', ['slug' => 'club-a', 'id' => $this->event->id]).'?token=ORDER-1');
        $this->assertSame('paid', $registration->fresh()->payment_status);

        $this->mock(PayPalGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()->with(\Mockery::any(), 'CAP-1', 96.0, 'GBP')->andReturn('REF-1'));
        app(EventPaymentService::class)->refund($registration->fresh(), $this->member, 'Cannot attend');

        $this->assertSame('refunded', $registration->fresh()->payment_status);
        $this->assertDatabaseHas('event_payment_log', ['registration_id' => $registration->id, 'action' => 'refunded', 'method' => 'PayPal refund']);
    }
}
