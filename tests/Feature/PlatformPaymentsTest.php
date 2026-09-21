<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubPlatformAccount;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use App\Services\Payment\StripeConnectGateway;
use App\Services\Payment\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PlatformPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_connect_secret';

    private Club $club;

    private Event $event;

    private User $admin;

    private User $member;

    private EventPaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'events.online_payments' => true,
            'platform_payments.enabled' => true,
            'platform_payments.stripe_secret' => 'sk_platform',
            'platform_payments.connect_client_id' => 'ca_test',
            'platform_payments.connect_webhook_secret' => self::SECRET,
            'platform_payments.commission_percent' => 2.0,
            'platform_payments.commission_fixed' => 0.30,
        ]);

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(20), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 50]);
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'card_online', 'label' => 'Card', 'config' => ['stripe_mode' => 'connect']]);
        $this->card = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);

        $this->admin = $this->join('admin');
        $this->member = $this->join('member');
    }

    private function join(string $role, ?Club $club = null): User
    {
        $user = User::factory()->create();
        ($club ?? $this->club)->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function account(array $overrides = []): ClubPlatformAccount
    {
        return ClubPlatformAccount::create($overrides + ['club_id' => $this->club->id, 'type' => 'standard', 'stripe_account_id' => 'acct_123', 'details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true, 'terms_accepted_at' => now()]);
    }

    private function book(): EventRegistration
    {
        return app(EventRegistrationService::class)->register($this->event, $this->member, ['payment_method' => $this->card->id, 'attendees' => [['name' => 'Mia']]]);
    }

    private function optionsPage(?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']));
    }

    // ---- connecting ---------------------------------------------------------------------------------------------

    public function test_an_officer_connects_an_existing_stripe_account_and_the_return_is_checked_against_who_left(): void
    {
        $this->mock(StripeConnectGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('authorizeUrl')->twice()->andReturn('https://connect.stripe.test/authorize');
            $mock->shouldReceive('accountIdForCode')->once()->with('code_1')->andReturn('acct_777');
            $mock->shouldReceive('status')->andReturn(['country' => 'GB', 'details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true, 'requirements' => []]);
        });

        $this->actingAs($this->admin)->post(route('admin.payment_options.stripe.connect', ['clubSlug' => 'club-a']), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://connect.stripe.test/authorize');
        $state = session('stripe_connect.state');

        $this->actingAs($this->admin)->get(route('stripe.connect.callback', ['state' => 'wrong', 'code' => 'code_1']))->assertForbidden();
        $this->assertNull(ClubPlatformAccount::first());

        $this->actingAs($this->admin)->post(route('admin.payment_options.stripe.connect', ['clubSlug' => 'club-a']), [], ['X-Inertia' => 'true']);
        $state = session('stripe_connect.state');
        $this->get(route('stripe.connect.callback', ['state' => $state, 'code' => 'code_1']))->assertRedirect(route('admin.payment_options.index', ['clubSlug' => 'club-a']));

        $account = ClubPlatformAccount::sole();
        $this->assertSame('acct_777', $account->stripe_account_id);
        $this->assertSame('standard', $account->type);
        $this->assertNull($account->terms_accepted_at, 'the terms still have to be accepted');
        $this->assertFalse($account->canTakePayments());
    }

    public function test_someone_else_cannot_finish_a_connection_started_by_an_officer(): void
    {
        $this->mock(StripeConnectGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('authorizeUrl')->andReturn('https://connect.stripe.test/authorize'));

        $this->actingAs($this->admin)->post(route('admin.payment_options.stripe.connect', ['clubSlug' => 'club-a']), [], ['X-Inertia' => 'true']);
        $state = session('stripe_connect.state');

        $this->actingAs($this->member)->withSession(['stripe_connect' => ['state' => $state, 'club' => $this->club->id]])->get(route('stripe.connect.callback', ['state' => $state, 'code' => 'x']))->assertForbidden();
        $this->assertNull(ClubPlatformAccount::first());
    }

    public function test_a_lodge_with_no_stripe_account_can_have_one_created_and_finish_setup_on_stripe(): void
    {
        $this->mock(StripeConnectGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('createExpressAccount')->once()->with('GB', \Mockery::any(), 'Club A')->andReturn('acct_new');
            $mock->shouldReceive('status')->andReturn(['country' => 'GB', 'details_submitted' => false, 'charges_enabled' => false, 'payouts_enabled' => false, 'requirements' => ['individual.id_number']]);
            $mock->shouldReceive('onboardingUrl')->andReturn('https://connect.stripe.test/setup');
        });

        $this->actingAs($this->admin)->post(route('admin.payment_options.stripe.express', ['clubSlug' => 'club-a']), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://connect.stripe.test/setup');
        $this->assertSame('express', ClubPlatformAccount::sole()->type);

        $this->optionsPage()->assertInertia(fn ($page) => $page->where('platform.account.charges_enabled', false)->where('platform.account.type', 'express')->where('platform.fee', '2% + £0.30 of each card payment'));
    }

    public function test_terms_must_be_accepted_and_disconnecting_switches_card_payments_off(): void
    {
        $this->account(['terms_accepted_at' => null]);
        $this->mock(StripeConnectGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('disconnect')->once()->with('acct_123'));
        $url = fn (string $name) => route("admin.payment_options.stripe.{$name}", ['clubSlug' => 'club-a']);

        $this->actingAs($this->admin)->post($url('terms'), [])->assertSessionHasErrors('agree');
        $this->assertNull(ClubPlatformAccount::sole()->terms_accepted_at);

        $this->actingAs($this->admin)->post($url('terms'), ['agree' => true])->assertSessionHasNoErrors();
        $this->assertTrue(ClubPlatformAccount::sole()->canTakePayments());

        $this->actingAs($this->admin)->post($url('disconnect'))->assertRedirect();
        $account = ClubPlatformAccount::sole();
        $this->assertFalse($account->canTakePayments());
        $this->assertNotNull($account->disconnected_at);
    }

    public function test_connecting_is_refused_when_platform_payments_are_off_and_only_billing_staff_of_that_lodge_may_do_it(): void
    {
        $this->mock(StripeConnectGateway::class)->shouldNotReceive('authorizeUrl');
        $url = route('admin.payment_options.stripe.connect', ['clubSlug' => 'club-a']);

        config(['platform_payments.enabled' => false]);
        $this->actingAs($this->admin)->post($url)->assertSessionHasErrors('stripe');

        config(['platform_payments.enabled' => true]);
        $this->actingAs($this->member)->post($url)->assertForbidden();
        $this->actingAs($this->join('admin', Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active'])))->post($url)->assertForbidden();
        $this->assertNull(session('stripe_connect'));
    }

    // ---- taking payments ----------------------------------------------------------------------------------------

    public function test_card_is_only_offered_through_the_platform_once_the_account_can_take_payments_and_terms_are_accepted(): void
    {
        $offered = fn () => app(EventPricing::class)->enabledMethods($this->event->fresh())->pluck('id')->all();

        $this->assertSame([], $offered(), 'nothing connected');

        $account = $this->account(['terms_accepted_at' => null]);
        $this->assertSame([], $offered(), 'terms not accepted');

        $account->update(['terms_accepted_at' => now(), 'charges_enabled' => false]);
        $this->assertSame([], $offered(), 'Stripe has not enabled payments');

        $account->update(['charges_enabled' => true]);
        $this->assertSame([$this->card->id], $offered());

        config(['platform_payments.enabled' => false]);
        $this->assertSame([], $offered(), 'switched off for the whole platform');
    }

    public function test_paying_creates_the_checkout_on_the_lodges_account_with_the_commission_taken(): void
    {
        $this->account();
        $this->book();
        $captured = null;
        $account = null;

        $this->mock(StripeGateway::class, function (MockInterface $mock) use (&$captured, &$account) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturnUsing(function ($method, $params, $connected) use (&$captured, &$account) {
                $captured = $params;
                $account = $connected;

                return ['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/cs_1'];
            });
        });

        $this->actingAs($this->member)->post(route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]), [], ['X-Inertia' => 'true'])->assertStatus(409);

        $this->assertSame('acct_123', $account);
        $this->assertSame(5000, $captured['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(130, $captured['payment_intent_data']['application_fee_amount'], '2% of 50.00 plus 0.30');
        $this->assertSame('130', $captured['metadata']['platform_fee']);
    }

    public function test_paying_with_the_lodges_own_keys_still_takes_no_commission(): void
    {
        $this->card->method->update(['config' => ['stripe_secret_key' => 'sk_test', 'stripe_webhook_secret' => 'whsec']]);
        $this->book();
        $captured = null;
        $account = 'unset';

        $this->mock(StripeGateway::class, function (MockInterface $mock) use (&$captured, &$account) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturnUsing(function ($method, $params, $connected = null) use (&$captured, &$account) {
                $captured = $params;
                $account = $connected;

                return ['id' => 'cs_2', 'url' => 'https://checkout.stripe.test/cs_2'];
            });
        });

        $this->actingAs($this->member)->post(route('member.events.pay', ['slug' => 'club-a', 'id' => $this->event->id]), [], ['X-Inertia' => 'true'])->assertStatus(409);

        $this->assertNull($account);
        $this->assertArrayNotHasKey('application_fee_amount', $captured['payment_intent_data']);
        $this->assertArrayNotHasKey('platform_fee', $captured['metadata']);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function connectWebhook(string $type, array $object, ?string $account = 'acct_123', ?string $secret = null)
    {
        $payload = json_encode(['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'account' => $account, 'data' => ['object' => $object]]);
        $time = time();
        $signature = "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", $secret ?? self::SECRET);

        return $this->call('POST', route('webhooks.stripe.connect'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    private function paidSession(EventRegistration $registration, array $overrides = []): array
    {
        return $overrides + ['id' => 'cs_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 5000, 'currency' => 'gbp', 'payment_intent' => 'pi_1', 'metadata' => ['registration_id' => (string) $registration->id, 'platform_fee' => '130']];
    }

    public function test_a_signed_payment_from_the_lodges_account_is_recorded_once_with_the_commission(): void
    {
        $this->account();
        $registration = $this->book();
        $registration->update(['stripe_session_id' => 'cs_1']);

        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration))->assertOk()->assertJson(['note' => null]);

        $this->assertSame('paid', $registration->fresh()->payment_status);
        $payment = PlatformPayment::sole();
        $this->assertSame($this->club->id, $payment->club_id);
        $this->assertSame('50.00', $payment->gross);
        $this->assertSame('1.30', $payment->commission);

        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration))->assertOk()->assertJson(['note' => 'already recorded']);
        $this->assertSame(1, PlatformPayment::count());
    }

    public function test_forged_or_mismatched_connect_events_do_nothing(): void
    {
        $this->account();
        $registration = $this->book();
        $registration->update(['stripe_session_id' => 'cs_1']);
        $other = Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        ClubPlatformAccount::create(['club_id' => $other->id, 'type' => 'standard', 'stripe_account_id' => 'acct_other', 'charges_enabled' => true, 'terms_accepted_at' => now()]);

        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration), 'acct_123', 'whsec_wrong')->assertStatus(400);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration), 'acct_other')->assertJson(['note' => 'no matching booking']);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration), 'acct_unknown')->assertJson(['note' => 'no matching account']);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration, ['id' => 'cs_forged']))->assertJson(['note' => 'no matching booking']);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration, ['amount_total' => 900000]))->assertJson(['note' => 'amount mismatch']);

        $this->assertSame('unpaid', $registration->fresh()->payment_status);
        $this->assertSame(0, PlatformPayment::count());

        config(['platform_payments.connect_webhook_secret' => null]);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration))->assertStatus(400);
    }

    public function test_stripe_reporting_a_change_to_the_account_updates_or_disconnects_it(): void
    {
        $account = $this->account(['charges_enabled' => false, 'payouts_enabled' => false]);

        $this->connectWebhook('account.updated', ['id' => 'acct_123', 'country' => 'gb', 'details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true, 'requirements' => ['currently_due' => []]])->assertOk();
        $this->assertTrue($account->fresh()->charges_enabled);
        $this->assertSame('GB', $account->fresh()->country);

        $this->connectWebhook('account.application.deauthorized', ['id' => 'acct_123'])->assertOk();
        $this->assertFalse($account->fresh()->canTakePayments());
        $this->assertNotNull($account->fresh()->disconnected_at);
    }

    public function test_a_refund_goes_to_the_lodges_account_and_returns_the_commission(): void
    {
        $this->account();
        $registration = $this->book();
        $registration->update(['stripe_session_id' => 'cs_1']);
        $this->connectWebhook('checkout.session.completed', $this->paidSession($registration));

        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()->with(\Mockery::any(), 'pi_1', 2500, 'acct_123')->andReturn('re_1'));
        app(EventPaymentService::class)->refund($registration->fresh(), $this->admin, 'Cannot attend', 25.0);

        $this->assertSame('25.00', PlatformPayment::sole()->refunded);
    }

    public function test_the_card_options_settings_keep_the_mode_and_drop_pasted_keys_when_connected(): void
    {
        $method = ClubPaymentMethod::sole();
        $base = ['type' => 'card_online', 'label' => 'Card', 'default_adjustment_kind' => 'none', 'default_adjustment_mode' => 'fixed', 'default_adjustment_amount' => 0, 'default_adjustment_scope' => 'per_person', 'is_active' => true];

        $this->actingAs($this->admin)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => $method->id]), $base + ['config' => ['stripe_mode' => 'keys', 'stripe_secret_key' => 'sk_live_abc', 'stripe_webhook_secret' => 'whsec_abc']])->assertSessionHasNoErrors();
        $this->assertSame('keys', $method->fresh()->config['stripe_mode']);
        $this->assertTrue($method->fresh()->hasStripeKeys());

        $this->actingAs($this->admin)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => $method->id]), $base + ['config' => ['stripe_mode' => 'connect', 'stripe_secret_key' => 'sk_live_abc']])->assertSessionHasNoErrors();
        $this->assertSame(['stripe_mode' => 'connect'], $method->fresh()->config);
    }

    public function test_the_superadmin_sees_lodges_and_commission_sets_a_rate_and_downloads_the_csv_and_nobody_else_can(): void
    {
        $account = $this->account();
        PlatformPayment::create(['club_id' => $this->club->id, 'payment_intent' => 'pi_a', 'currency' => 'GBP', 'gross' => 50, 'commission' => 1.3, 'refunded' => 10]);
        PlatformPayment::create(['club_id' => $this->club->id, 'payment_intent' => 'pi_b', 'currency' => 'GBP', 'gross' => 30, 'commission' => 0.9]);
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get(route('superadmin.platform_payments.index'))->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/PlatformPayments/Index')
            ->where('lodges.0.club', 'Club A')
            ->where('lodges.0.ready', true)
            ->where('lodges.0.totals.0.gross', '80.00')
            ->where('lodges.0.totals.0.commission', '2.20')
            ->where('lodges.0.totals.0.refunded', '10.00')
            ->where('byMonth.0.commission', '2.20')
            ->where('byMonth.0.payments', 2));

        $this->actingAs($super)->put(route('superadmin.platform_payments.update', $account->id), ['commission_percent' => 1.5, 'commission_fixed' => 0.2])->assertSessionHasNoErrors();
        $this->assertSame('1.50', $account->fresh()->commission_percent);
        $this->actingAs($super)->put(route('superadmin.platform_payments.update', $account->id), ['commission_percent' => 150])->assertSessionHasErrors('commission_percent');
        $this->actingAs($super)->put(route('superadmin.platform_payments.update', $account->id), ['commission_percent' => null, 'commission_fixed' => null]);
        $this->assertNull($account->fresh()->commission_percent);

        $csv = $this->actingAs($super)->get(route('superadmin.platform_payments.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Club A', $csv);
        $this->assertStringContainsString('1.30', $csv);

        foreach ([$this->admin, $this->member] as $user) {
            $this->actingAs($user)->get(route('superadmin.platform_payments.index'))->assertRedirect();
            $this->actingAs($user)->put(route('superadmin.platform_payments.update', $account->id), ['commission_percent' => 0])->assertRedirect();
        }

        $this->assertNull($account->fresh()->commission_percent);
    }
}
