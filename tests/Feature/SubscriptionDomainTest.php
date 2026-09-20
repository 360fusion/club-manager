<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected SubscriptionTier $fullTier;

    protected SubscriptionTier $countryTier;

    protected Member $member1;

    protected Member $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members', 'subscriptions'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->clubs()->attach($this->club->id, ['role' => 'admin']);

        $this->fullTier = SubscriptionTier::create([
            'club_id' => $this->club->id,
            'name' => 'Full Subscribing Member',
            'annual_amount' => 160.00,
            'is_active' => true,
        ]);

        $this->countryTier = SubscriptionTier::create([
            'club_id' => $this->club->id,
            'name' => 'Country Member',
            'annual_amount' => 90.00,
            'is_active' => true,
        ]);

        $this->member1 = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Charles',
            'last_name' => 'Darwin',
            'email' => 'charles@example.com',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
            'subscription_tier_id' => $this->fullTier->id,
        ]);

        $this->member2 = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Alexander',
            'last_name' => 'Fleming',
            'email' => 'alexander@example.com',
            'masonic_rank' => 'WBro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Treasurer,
            'subscription_tier_id' => $this->countryTier->id,
        ]);
    }

    public function test_can_create_subscription_tier(): void
    {
        $tier = SubscriptionTier::create([
            'club_id' => $this->club->id,
            'name' => 'Honorary Member',
            'annual_amount' => 0.00,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('club_acc_subscription_tiers', [
            'id' => $tier->id,
            'name' => 'Honorary Member',
            'annual_amount' => 0.00,
        ]);
    }

    public function test_annual_billing_run_generates_invoices_for_active_members(): void
    {
        $service = new SubscriptionBillingService;
        $result = $service->generateAnnualBillingRun($this->club, 2026, Carbon::parse('2026-04-01'));

        $this->assertEquals(2, $result['created_count']);
        $this->assertEquals(250.00, $result['total_billed']);

        $this->assertDatabaseHas('club_acc_member_subscriptions', [
            'member_id' => $this->member1->id,
            'billing_year' => 2026,
            'amount_due' => 160.00,
            'status' => 'unpaid',
            'invoice_reference' => sprintf('INV-2026-M%04d', $this->member1->id),
        ]);

        $this->assertDatabaseHas('club_acc_member_subscriptions', [
            'member_id' => $this->member2->id,
            'billing_year' => 2026,
            'amount_due' => 90.00,
            'status' => 'unpaid',
            'invoice_reference' => sprintf('INV-2026-M%04d', $this->member2->id),
        ]);
    }

    public function test_record_payment_updates_subscription_status(): void
    {
        $service = new SubscriptionBillingService;
        $service->generateAnnualBillingRun($this->club, 2026);

        $sub = MemberSubscription::where('member_id', $this->member1->id)->first();

        // Partial payment
        $service->recordPayment($sub, 60.00, 'BAC-001');
        $sub->refresh();
        $this->assertEquals(60.00, $sub->amount_paid);
        $this->assertEquals(100.00, $sub->balance_due);
        $this->assertEquals(SubscriptionStatus::PartiallyPaid, $sub->status);

        // Remaining payment
        $service->recordPayment($sub, 100.00, 'BAC-002');
        $sub->refresh();
        $this->assertEquals(160.00, $sub->amount_paid);
        $this->assertEquals(0.00, $sub->balance_due);
        $this->assertEquals(SubscriptionStatus::Paid, $sub->status);
    }

    public function test_rule_181_arrears_audit_flags_overdue_members(): void
    {
        // Overdue subscription past 90 days
        $overdueSub = MemberSubscription::create([
            'club_id' => $this->club->id,
            'member_id' => $this->member1->id,
            'tier_id' => $this->fullTier->id,
            'billing_year' => 2025,
            'due_date' => Carbon::now()->subDays(120),
            'amount_due' => 160.00,
            'amount_paid' => 0.00,
            'status' => SubscriptionStatus::Unpaid,
            'invoice_reference' => 'INV-2025-M001',
        ]);

        $service = new SubscriptionBillingService;
        $arrearsList = $service->checkRule181Arrears($this->club, 90);

        $this->assertCount(1, $arrearsList);
        $overdueSub->refresh();
        $this->assertEquals(SubscriptionStatus::ArrearsWarning, $overdueSub->status);
    }

    public function test_subscription_index_livewire_component(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(SubscriptionIndex::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200)
            ->assertSee('Subscriptions', false)
            ->call('runAnnualBilling')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_member_subscriptions', [
            'member_id' => $this->member1->id,
            'billing_year' => 2026,
            'amount_due' => 160.00,
        ]);
    }

    public function test_billing_portal_route_redirection(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('billing.portal', ['clubSlug' => $this->club->slug]));

        $response->assertRedirect(route('billing.index', ['clubSlug' => $this->club->slug]));
    }
}
