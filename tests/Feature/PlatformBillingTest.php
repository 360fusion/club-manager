<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\BillingChecks;
use App\Support\PlanCatalog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** A club's subscription to the platform: the plan it is on, the billing page, and Stripe's messages */
class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    private function proPrice(): void
    {
        config(['plans.plans.pro.prices.monthly' => 'price_pro_monthly']);
    }

    private function subscribe(Club $club, string $price, array $extra = []): void
    {
        $club->forceFill(['stripe_id' => 'cus_123'])->save();
        $club->subscriptions()->create($extra + [
            'type' => 'default',
            'stripe_id' => 'sub_123',
            'stripe_status' => 'active',
            'stripe_price' => $price,
            'quantity' => 1,
        ]);
    }

    public function test_a_club_with_no_subscription_is_on_the_free_plan(): void
    {
        $club = Club::factory()->create();

        $this->assertSame('free', PlanCatalog::forClub($club)['key']);
    }

    public function test_the_plan_is_found_from_the_stripe_price_a_club_pays_for(): void
    {
        $this->proPrice();
        $club = Club::factory()->create();
        $this->subscribe($club, 'price_pro_monthly');

        $plan = PlanCatalog::forClub($club);
        $this->assertSame('pro', $plan['key']);
        $this->assertSame('monthly', $plan['period']);
    }

    public function test_a_cancelled_or_unknown_price_does_not_give_a_paid_plan(): void
    {
        $this->proPrice();
        $ended = Club::factory()->create();
        $this->subscribe($ended, 'price_pro_monthly', ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);
        $unknown = Club::factory()->create();
        $this->subscribe($unknown, 'price_nobody_knows', ['stripe_id' => 'sub_456']);

        $this->assertSame('free', PlanCatalog::forClub($ended)['key']);
        $this->assertSame('free', PlanCatalog::forClub($unknown)['key']);
    }

    public function test_a_club_keeps_its_accounting_invoices_apart_from_its_stripe_ones(): void
    {
        $club = Club::factory()->create();

        $this->assertInstanceOf(HasMany::class, $club->invoices());
        $this->assertCount(0, $club->stripeInvoices(true));
    }

    public function test_the_billing_page_shows_the_real_plan_and_no_made_up_invoices(): void
    {
        config(['cashier.secret' => null]);
        $club = Club::factory()->create();
        $admin = $this->makeClubAdmin(User::factory()->create(), $club);

        $this->actingAs($admin)->get(route('billing.index', $club->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentPlan.name', 'Free')
                ->where('currentPlan.status', 'free')
                ->where('currentPlan.price', null)
                ->where('billingHistory', []));
    }

    public function test_the_billing_page_reports_a_paid_plan_and_a_cancelled_one_that_is_running_out(): void
    {
        config(['cashier.secret' => null]);
        $this->proPrice();
        $club = Club::factory()->create();
        $admin = $this->makeClubAdmin(User::factory()->create(), $club);
        $this->subscribe($club, 'price_pro_monthly', ['ends_at' => now()->addDays(5)]);

        $this->actingAs($admin)->get(route('billing.index', $club->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentPlan.name', 'Pro')
                ->where('currentPlan.status', 'cancelling')
                ->where('currentPlan.billing_cycle', 'Monthly')
                ->where('currentPlan.next_billing_date', now()->addDays(5)->format('j M Y')));
    }

    public function test_a_failed_checkout_does_not_blame_the_keys_or_leak_the_error(): void
    {
        $club = Club::factory()->create();
        $admin = $this->makeClubAdmin(User::factory()->create(), $club);

        $response = $this->actingAs($admin)->from(route('billing.index', $club->slug))
            ->post(route('billing.checkout', $club->slug), ['price_id' => 'price_x']);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertStringNotContainsString('SQLSTATE', session('error'));
    }

    public function test_stripe_webhook_refuses_everything_when_no_signing_secret_is_set(): void
    {
        config(['cashier.webhook.secret' => null]);

        $this->postJson(route('webhooks.stripe.platform'), ['id' => 'evt_1', 'type' => 'customer.subscription.created'])->assertForbidden();
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_stripe_webhook_refuses_a_message_with_a_bad_signature(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $this->postJson(route('webhooks.stripe.platform'), ['id' => 'evt_1', 'type' => 'invoice.paid'], ['Stripe-Signature' => 't=1,v1=bad'])->assertForbidden();
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_a_stripe_message_that_arrives_twice_is_only_acted_on_once(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);
        $body = json_encode(['id' => 'evt_dup', 'type' => 'customer.updated', 'data' => ['object' => ['id' => 'cus_none', 'customer' => 'cus_none']]]);
        $signature = fn () => 't='.($t = time()).',v1='.hash_hmac('sha256', $t.'.'.$body, 'whsec_test');

        foreach ([1, 2] as $_) {
            $this->call('POST', route('webhooks.stripe.platform'), [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $signature(),
                'CONTENT_TYPE' => 'application/json',
            ], $body)->assertOk();
        }

        $this->assertSame(1, DB::table('stripe_events')->where('stripe_id', 'evt_dup')->count());
    }

    public function test_billing_doctor_flags_unset_price_ids_and_passes_when_everything_is_set(): void
    {
        config(['cashier.key' => 'pk', 'cashier.secret' => 'sk', 'cashier.webhook.secret' => 'whsec', 'plans.plans.pro.prices' => ['monthly' => null, 'annual' => null]]);
        $this->artisan('billing:doctor')->assertFailed();

        config(['plans.plans.pro.prices' => ['monthly' => 'price_a', 'annual' => 'price_b']]);
        $this->artisan('billing:doctor')->assertSuccessful();
    }

    public function test_two_plans_sharing_one_price_id_is_flagged(): void
    {
        config(['plans.plans.pro.prices' => ['monthly' => 'price_same', 'annual' => 'price_same']]);

        $bad = collect(BillingChecks::run())->where('ok', false)->pluck('detail')->implode(' ');
        $this->assertStringContainsString('same id as Pro (monthly)', $bad);
    }
}
