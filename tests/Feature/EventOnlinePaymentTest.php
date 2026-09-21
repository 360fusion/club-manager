<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventBookingMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentLog;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use App\Services\Payment\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class EventOnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private Club $club;

    private Event $event;

    private User $member;

    private EventPaymentMethod $online;

    private EventPaymentMethod $later;

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.online_payments' => true]);
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(20), 'status' => 'upcoming', 'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Public, 'allow_public_registration' => true, 'requires_payment' => true, 'price' => 50, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 2]);

        $card = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'card_online', 'label' => 'Pay online', 'sort_order' => 0, 'default_adjustment_kind' => 'discount', 'default_adjustment_amount' => 3, 'config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => self::SECRET]]);
        $later = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'pay_later', 'label' => 'Pay later', 'sort_order' => 1, 'due_days' => 7]);
        $this->online = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $card->id, 'is_enabled' => true]);
        $this->later = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $later->id, 'is_enabled' => true]);

        $this->member = User::factory()->create(['name' => 'Mia Member']);
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
    }

    private function book(EventPaymentMethod $option): EventRegistration
    {
        return app(EventRegistrationService::class)->register($this->event, $this->member, ['payment_method' => $option->id, 'attendees' => [['name' => 'Mia'], ['name' => 'Gary', 'is_guest' => true]]]);
    }

    private function fakeCheckout(?array &$captured = null): void
    {
        $this->mock(StripeGateway::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturnUsing(function ($method, $params) use (&$captured) {
                $captured = $params;

                return ['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.test/pay/cs_test_123'];
            });
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function webhook(string $sessionId, EventRegistration $registration, int $amountMinor, array $overrides = [], ?string $secret = null, string $type = 'checkout.session.completed', ?int $clubId = null)
    {
        $payload = json_encode([
            'id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type,
            'data' => ['object' => array_merge(['id' => $sessionId, 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => $amountMinor, 'payment_intent' => 'pi_test_1', 'metadata' => ['registration_id' => (string) $registration->id]], $overrides)],
        ]);
        $timestamp = time();
        $signature = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::SECRET);

        return $this->call('POST', route('webhooks.stripe.club', ['clubId' => $clubId ?? $this->club->id]), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    public function test_paying_now_moves_a_pay_later_booking_to_the_online_price_and_creates_the_checkout_for_what_is_owed(): void
    {
        $registration = $this->book($this->later);
        $this->assertSame('102.00', $registration->total);

        $captured = null;
        $this->fakeCheckout($captured);

        $response = $this->actingAs($this->member)->post(route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]), [], ['X-Inertia' => 'true']);

        $response->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/pay/cs_test_123');

        $registration = $registration->fresh();
        $this->assertSame('96.00', $registration->total, 'two people, 3 off each, plus the 2.00 booking fee');
        $this->assertSame($this->online->payment_method_id, $registration->payment_method_id);
        $this->assertNull($registration->due_at);
        $this->assertSame('cs_test_123', $registration->stripe_session_id);

        $this->assertSame('payment', $captured['mode']);
        $this->assertSame(9600, $captured['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('gbp', $captured['line_items'][0]['price_data']['currency']);
        $this->assertSame((string) $registration->id, $captured['metadata']['registration_id']);
        $this->assertStringContainsString('payment=success', $captured['success_url']);
    }

    public function test_a_guest_can_pay_from_their_private_link_and_nobody_else_can_pay_for_them(): void
    {
        $this->event->update(['booking_fee_type' => 'none']);
        $registration = app(EventRegistrationService::class)->register($this->event, null, ['payment_method' => $this->later->id, 'contact_name' => 'Pat', 'contact_email' => 'pat@example.test', 'attendees' => [['name' => 'Pat']]]);
        $token = $registration->plainToken;

        $captured = null;
        $this->fakeCheckout($captured);
        $this->post(route('public.event.booking.pay', ['clubSlug' => 'club-a', 'token' => $token]), [], ['X-Inertia' => 'true'])->assertStatus(409);
        $this->assertSame(4700, $captured['line_items'][0]['price_data']['unit_amount'], '50 less 3 online');
        $this->assertSame('pat@example.test', $captured['customer_email']);

        $this->post(route('public.event.booking.pay', ['clubSlug' => 'club-a', 'token' => $token.'x']))->assertNotFound();
        $this->post(route('public.event.booking.pay', ['clubSlug' => 'club-a', 'token' => 'short']))->assertNotFound();
    }

    public function test_card_payment_is_refused_when_it_is_switched_off_not_ready_or_the_booking_is_settled(): void
    {
        $registration = $this->book($this->later);
        $url = route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]);
        $this->mock(StripeGateway::class)->shouldNotReceive('createCheckoutSession');

        config(['events.online_payments' => false]);
        $this->actingAs($this->member)->post($url)->assertSessionHasErrors('payment');

        config(['events.online_payments' => true]);
        $this->online->method->update(['config' => ['stripe_secret_key' => 'sk_test_x']]);
        $this->actingAs($this->member)->post($url)->assertSessionHasErrors('payment');

        $this->online->method->update(['config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => self::SECRET]]);
        $registration->update(['payment_status' => 'paid', 'amount_paid' => 102]);
        $this->actingAs($this->member)->post($url)->assertSessionHasErrors('payment');
    }

    public function test_a_signed_stripe_payment_marks_the_booking_paid_logs_stripe_and_posts_the_income_once(): void
    {
        $registration = $this->book($this->online);
        $registration->update(['stripe_session_id' => 'cs_test_123']);
        $this->assertSame('96.00', $registration->total);

        $this->webhook('cs_test_123', $registration, 9600)->assertOk()->assertJsonPath('note', null);

        $registration = $registration->fresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('96.00', $registration->amount_paid);
        $this->assertSame('pi_test_1', $registration->stripe_payment_intent);

        $log = EventPaymentLog::sole();
        $this->assertSame('stripe_paid', $log->action);
        $this->assertSame('stripe_webhook', $log->source);
        $this->assertNull($log->user_id);
        $this->assertSame('pi_test_1', $log->external_id);

        // Stripe sends the same notification more than once; the payment is only ever recorded once.
        $this->webhook('cs_test_123', $registration, 9600)->assertOk()->assertJsonPath('note', 'already recorded');
        $this->assertSame(1, EventPaymentLog::count());
        $this->assertSame('96.00', $registration->fresh()->amount_paid);
    }

    public function test_forged_replayed_or_mismatched_notifications_change_nothing(): void
    {
        $registration = $this->book($this->online);
        $registration->update(['stripe_session_id' => 'cs_test_123']);
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $this->webhook('cs_test_123', $registration, 9600, [], 'whsec_wrong')->assertStatus(400);
        $this->webhook('cs_test_123', $registration, 9600, [], null, 'checkout.session.completed', 999999)->assertStatus(400);
        $this->webhook('cs_test_123', $registration, 9600, [], null, 'checkout.session.completed', $other->id)->assertStatus(400);
        $this->call('POST', route('webhooks.stripe.club', ['clubId' => $this->club->id]), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"type":"checkout.session.completed"}')->assertStatus(400);

        $this->webhook('cs_someone_elses', $registration, 9600)->assertOk()->assertJsonPath('note', 'no matching booking');
        $this->webhook('cs_test_123', $registration, 999900, ['payment_intent' => 'pi_too_big'])->assertOk()->assertJsonPath('note', 'amount mismatch');
        $this->webhook('cs_test_123', $registration, 9600, ['payment_status' => 'unpaid', 'payment_intent' => 'pi_unpaid'])->assertOk()->assertJsonPath('note', 'not paid yet');
        $this->webhook('cs_test_123', $registration, 9600, ['payment_intent' => 'pi_refund'], null, 'charge.refunded')->assertOk()->assertJsonPath('note', 'ignored event type');
        $this->assertSame(0, EventPaymentLog::count(), 'none of the above recorded a payment');
        $this->assertSame('unpaid', $registration->fresh()->payment_status);

        $this->webhook('cs_test_123', $registration, 1000, ['payment_intent' => 'pi_part'])->assertOk()->assertJsonPath('note', null);
        $this->assertSame('10.00', $registration->fresh()->amount_paid);
        $this->assertSame('part_paid', $registration->fresh()->payment_status);
        $this->assertSame(1, EventPaymentLog::count(), 'only the genuine part payment was recorded');
    }

    public function test_a_notification_cannot_pay_another_clubs_booking(): void
    {
        $registration = $this->book($this->online);
        $registration->update(['stripe_session_id' => 'cs_test_123']);
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        ClubPaymentMethod::create(['club_id' => $other->id, 'type' => 'card_online', 'label' => 'Online', 'config' => ['stripe_secret_key' => 'sk', 'stripe_webhook_secret' => 'whsec_other']]);

        $this->webhook('cs_test_123', $registration, 9600, [], 'whsec_other', 'checkout.session.completed', $other->id)->assertOk()->assertJsonPath('note', 'no matching booking');
        $this->assertSame('unpaid', $registration->fresh()->payment_status);
    }

    public function test_refunding_a_card_payment_goes_through_stripe_and_a_stripe_failure_records_nothing(): void
    {
        $registration = $this->book($this->online);
        $registration->update(['stripe_session_id' => 'cs_test_123']);
        $this->webhook('cs_test_123', $registration, 9600)->assertOk();

        $treasurer = User::factory()->create();
        $this->club->users()->attach($treasurer->id, ['role' => 'treasurer', 'status' => 'active']);
        $url = route('admin.events.payment.refund', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]);

        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()->andThrow(ValidationException::withMessages(['amount' => 'Stripe could not refund this payment: card closed'])));
        $this->actingAs($treasurer)->post($url, ['comment' => 'Cancelled'])->assertSessionHasErrors('amount');
        $this->assertSame('paid', $registration->fresh()->payment_status);
        $this->assertSame(1, EventPaymentLog::count());

        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()->withArgs(fn ($method, $intent, $minor) => $intent === 'pi_test_1' && $minor === 9600)->andReturn('re_test_1'));
        $this->actingAs($treasurer)->post($url, ['comment' => 'Cancelled, full refund'])->assertSessionHasNoErrors();

        $this->assertSame('refunded', $registration->fresh()->payment_status);
        $this->assertSame('Stripe refund', EventPaymentLog::orderByDesc('id')->first()->method);
    }

    public function test_the_shared_cashier_webhook_route_is_gone_and_the_setup_page_gives_the_clubs_own_address(): void
    {
        $this->post('/stripe/webhook')->assertNotFound();

        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->where('stripeWebhookUrl', route('webhooks.stripe.club', ['clubId' => $this->club->id]))
            ->where('methods.0.ready_for_cards', true)
            ->where('onlinePaymentsLive', true));
    }

    public function test_the_booking_payload_offers_pay_now_with_the_saving(): void
    {
        $this->book($this->later);

        $this->actingAs($this->member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->where('events.0.user_rsvp.payment.online.can_pay', true)
            ->where('events.0.user_rsvp.payment.online.total', 96)
            ->where('events.0.user_rsvp.payment.online.saving', 6));
    }

    public function test_choosing_to_pay_online_while_booking_goes_straight_to_stripe_for_members_and_guests(): void
    {
        $captured = null;
        $this->fakeCheckout($captured);

        $this->actingAs($this->member)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), [
            'attendance_status' => 'attending', 'payment_method' => $this->online->id, 'attendees' => [['name' => 'Mia']],
        ], ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/pay/cs_test_123');

        $this->assertSame(4900, $captured['line_items'][0]['price_data']['unit_amount'], '50 less 3, plus the 2.00 booking fee');
        $this->assertSame('cs_test_123', EventRegistration::sole()->stripe_session_id);
        $this->assertStringContainsString('/members/club-a/events?payment=success', $captured['success_url']);
    }

    public function test_a_guest_who_chooses_online_is_sent_to_stripe_and_returns_to_their_private_page(): void
    {
        $captured = null;
        $this->fakeCheckout($captured);
        Mail::fake();

        $this->post(route('public.event.register', ['clubSlug' => 'club-a', 'eventSlug' => 'dinner']), [
            'contact_name' => 'Pat Guest', 'contact_email' => 'pat@example.test', 'payment_method' => $this->online->id, 'website' => '',
            'attendees' => [['name' => '']],
        ], ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location');

        $this->assertMatchesRegularExpression('#/site/club-a/booking/[A-Za-z0-9]{40}\?payment=success$#', $captured['success_url']);
        Mail::assertQueued(EventBookingMail::class);
    }

    public function test_if_stripe_cannot_start_the_booking_is_kept_and_the_person_told(): void
    {
        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('createCheckoutSession')->once()->andThrow(ValidationException::withMessages(['payment' => 'Online payment is not available right now.'])));

        $this->actingAs($this->member)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), [
            'attendance_status' => 'attending', 'payment_method' => $this->online->id, 'attendees' => [['name' => 'Mia']],
        ])->assertSessionHasErrors('payment');

        $this->assertSame(1, EventRegistration::count(), 'the booking is not lost');
        $this->assertSame('unpaid', EventRegistration::sole()->payment_status);
    }
}
