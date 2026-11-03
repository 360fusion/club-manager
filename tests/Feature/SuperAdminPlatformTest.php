<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\SuperAdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\TestCase;

/** The platform lists a superadmin runs the service from, and the log of what they change */
class SuperAdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['superadmin.users.index', 'superadmin.clubs.index', 'superadmin.stripe_events.index', 'superadmin.actions.index', 'superadmin.health.index'];

    private function superadmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_only_a_superadmin_can_open_the_platform_pages(): void
    {
        foreach (self::PAGES as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }

        // actingAs stays in force for the rest of the test, so the signed-in checks come after the guest ones.
        $member = User::factory()->create();
        foreach (self::PAGES as $name) {
            $this->actingAs($member)->get(route($name))->assertRedirect(route('home'));
        }
    }

    public function test_every_platform_page_opens_for_a_superadmin(): void
    {
        $admin = $this->superadmin();

        foreach (self::PAGES as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('SuperAdmin/Platform/Table')->has('columns'));
        }
    }

    public function test_the_users_list_searches_and_says_who_is_closing(): void
    {
        $admin = $this->superadmin();
        User::factory()->create(['name' => 'Alice Findable', 'email' => 'alice@example.test']);
        User::factory()->create(['name' => 'Bob Other', 'email' => 'bob@example.test', 'closure_requested_at' => now()]);

        $this->actingAs($admin)->get(route('superadmin.users.index', ['q' => 'findable']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.name', 'Alice Findable')->where('rows.0.status', 'Active'));

        $this->actingAs($admin)->get(route('superadmin.users.index', ['q' => 'bob']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.status', 'Closing '.now()->addDays(30)->format('j M Y')));
    }

    public function test_the_clubs_list_shows_each_clubs_plan_and_stripe_status(): void
    {
        config(['plans.plans.pro.prices.monthly' => 'price_pro']);
        $paying = Club::factory()->create(['name' => 'Paying Lodge', 'stripe_id' => 'cus_9']);
        $paying->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_9', 'stripe_status' => 'active', 'stripe_price' => 'price_pro', 'quantity' => 1]);
        Club::factory()->create(['name' => 'Free Lodge']);

        $this->actingAs($this->superadmin())->get(route('superadmin.clubs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Paying Lodge')['plan'] === 'Pro'
                    && collect($rows)->firstWhere('name', 'Paying Lodge')['subscription'] === 'active'
                    && collect($rows)->firstWhere('name', 'Free Lodge')['plan'] === 'Free'));
    }

    public function test_the_stripe_events_page_lists_what_the_webhook_logged(): void
    {
        DB::table('stripe_events')->insert(['stripe_id' => 'evt_seen', 'type' => 'invoice.paid', 'customer_id' => 'cus_1', 'received_at' => now()]);

        $this->actingAs($this->superadmin())->get(route('superadmin.stripe_events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.type', 'invoice.paid')->where('rows.0.stripe_id', 'evt_seen'));
    }

    public function test_changes_and_exports_are_logged_but_looking_is_not(): void
    {
        $admin = $this->superadmin();
        $club = Club::factory()->create();

        $this->actingAs($admin)->get(route('superadmin.users.index'))->assertOk();
        $this->assertSame(0, SuperAdminAction::count());

        $this->actingAs($admin)->get(route('superadmin.platform_payments.export'));
        $this->assertDatabaseHas('superadmin_actions', ['user_id' => $admin->id, 'route_name' => 'superadmin.platform_payments.export', 'method' => 'GET']);

        $this->actingAs($admin)->put(route('superadmin.platform_payments.update', $club->id), ['commission_percent' => 7, 'commission_fixed' => 0.5]);
        $this->assertDatabaseHas('superadmin_actions', ['user_id' => $admin->id, 'route_name' => 'superadmin.platform_payments.update', 'method' => 'PUT']);
    }

    public function test_the_log_does_not_keep_what_was_typed(): void
    {
        $admin = $this->superadmin();
        $club = Club::factory()->create();

        $this->actingAs($admin)->put(route('superadmin.platform_payments.update', $club->id), ['commission_percent' => 7.77, 'commission_fixed' => 0.5]);

        $stored = json_encode(DB::table('superadmin_actions')->get());
        $this->assertStringNotContainsString('7.77', $stored);
    }

    public function test_a_log_entry_cannot_be_changed_or_removed(): void
    {
        $admin = $this->superadmin();
        $entry = SuperAdminAction::create(['user_id' => $admin->id, 'method' => 'POST', 'path' => '/superadmin/x', 'status' => 200]);

        try {
            $entry->update(['status' => 500]);
            $this->fail('update should have been refused');
        } catch (LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('delete should have been refused');
        } catch (LogicException) {
        }

        $this->assertSame(200, $entry->fresh()->status);
    }

    public function test_the_health_page_runs_the_billing_checks(): void
    {
        config(['cashier.secret' => null]);

        $this->actingAs($this->superadmin())->get(route('superadmin.health.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Stripe keys')['ok'] === false));
    }
}
