<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Exceptions\LedgerRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingReports;
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

    public function test_billing_run_skips_historical_members_and_never_invents_an_amount(): void
    {
        $this->member1->update(['membership_status' => MembershipStatus::Historical]);
        $this->member2->update(['subscription_tier_id' => null]);
        SubscriptionTier::where('club_id', $this->club->id)->update(['is_active' => false]);

        $result = (new SubscriptionBillingService)->generateAnnualBillingRun($this->club, 2026);

        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['no_rate_count']);
        $this->assertDatabaseCount('club_acc_member_subscriptions', 0);
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

    /**
     * @return array<string, float>
     */
    private function books(): array
    {
        return app(AccountingReports::class)->getFinancialSummary($this->club);
    }

    public function test_billing_and_payments_are_in_the_books_and_a_waiver_takes_what_is_left_back_out(): void
    {
        $service = new SubscriptionBillingService;
        $service->generateAnnualBillingRun($this->club, 2026);

        $this->assertEquals(250.00, $this->books()['total_revenue'], 'dues billed are income');
        $this->assertEquals(250.00, $this->books()['total_assets'], 'and owed to the lodge');

        $service->recordPayment(MemberSubscription::where('member_id', $this->member1->id)->first(), 60.00, 'BAC-1');
        $this->assertEquals(250.00, $this->books()['total_revenue']);
        $this->assertEquals(250.00, $this->books()['total_assets'], '£60 moved from owed to cash');
        $this->assertSame(60.0, (float) JournalEntry::where('source_type', 'MemberSubscriptionPayment')->firstOrFail()->total_debit);

        $service->waive(MemberSubscription::where('member_id', $this->member2->id)->first());
        $this->assertEquals(160.00, $this->books()['total_revenue'], 'the £90 waived is no longer income');
        $this->assertEquals(160.00, $this->books()['total_assets']);
        $this->assertSame(SubscriptionStatus::Waived, MemberSubscription::where('member_id', $this->member2->id)->first()->status);
    }

    public function test_a_payment_of_more_than_is_owed_is_refused_and_leaves_nothing_behind(): void
    {
        $service = new SubscriptionBillingService;
        $service->generateAnnualBillingRun($this->club, 2026);
        $sub = MemberSubscription::where('member_id', $this->member1->id)->first();
        $entries = JournalEntry::count();

        $this->assertThrows(fn () => $service->recordPayment($sub, 500.00, 'BAC-BIG'), LedgerRuleException::class, 'more than the £160.00 still owed');

        $this->assertEquals(0.00, $sub->fresh()->amount_paid);
        $this->assertSame($entries, JournalEntry::count());

        $service->waive($sub);
        $this->assertThrows(fn () => $service->recordPayment($sub->fresh(), 10.00), LedgerRuleException::class, 'Nothing is owed');
    }

    public function test_the_payment_component_shows_the_refusal_on_the_amount_field(): void
    {
        $this->actingAs($this->adminUser);
        (new SubscriptionBillingService)->generateAnnualBillingRun($this->club, 2026);
        $sub = MemberSubscription::where('member_id', $this->member1->id)->first();

        Livewire::test(SubscriptionIndex::class, ['clubSlug' => $this->club->slug])
            ->call('openPaymentModal', $sub->id)
            ->set('payment_amount', '500')
            ->call('recordPayment')
            ->assertHasErrors('payment_amount');

        $this->assertEquals(0.00, $sub->fresh()->amount_paid);
    }

    public function test_a_subscription_billed_before_the_books_were_kept_is_caught_up_when_it_is_paid(): void
    {
        $old = MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $this->member1->id, 'billing_year' => 2025, 'due_date' => '2025-04-01', 'amount_due' => 160.00, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid, 'invoice_reference' => 'INV-2025-OLD']);

        (new SubscriptionBillingService)->recordPayment($old, 160.00, 'BAC-OLD');

        $this->assertEquals(160.00, $this->books()['total_revenue']);
        $this->assertEquals(160.00, $this->books()['total_assets'], 'cash in, nothing left owed, no negative receivable');
    }

    public function test_matching_a_bank_line_to_a_subscription_counts_the_income_once_and_uses_that_bank_accounts_ledger_account(): void
    {
        $service = new SubscriptionBillingService;
        $service->generateAnnualBillingRun($this->club, 2026);
        $sub = MemberSubscription::where('member_id', $this->member1->id)->first();
        $ledger = Account::create(['club_id' => $this->club->id, 'code' => '1010', 'name' => 'Savings', 'type' => 'asset', 'is_active' => true]);
        $bank = BankAccount::create(['club_id' => $this->club->id, 'account_id' => $ledger->id, 'bank_name' => 'Lloyds', 'account_name' => 'Savings', 'account_type' => 'savings', 'is_active' => true]);
        $import = BankImport::create(['club_id' => $this->club->id, 'filename' => 'x.csv', 'total_lines' => 1, 'total_amount' => 160]);
        $line = BankTransaction::create(['club_id' => $this->club->id, 'bank_import_id' => $import->id, 'bank_account_id' => $bank->id, 'transaction_date' => '2026-10-05', 'raw_description' => 'BACS C DARWIN SUBS', 'amount' => 160.00, 'transaction_hash' => 'sub-hash', 'status' => BankTransactionStatus::Unmatched]);

        app(BankReconciliationMatcherService::class)->reconcileTransaction($line, 'member_subscription', $sub->id);

        $this->assertSame(SubscriptionStatus::Paid, $sub->fresh()->status);
        $this->assertEquals(250.00, $this->books()['total_revenue'], 'billed once, not again when the money arrived');
        $payment = JournalEntry::where('source_type', 'MemberSubscriptionPayment')->firstOrFail();
        $this->assertContains($ledger->id, $payment->items->pluck('account_id')->all());
        $this->assertSame('2026-10-05', $payment->entry_date->toDateString());
    }

    public function test_a_tier_can_only_be_given_to_a_member_from_the_same_lodge(): void
    {
        $this->actingAs($this->adminUser);
        $other = Club::create(['name' => 'Other Lodge', 'slug' => 'other-lodge', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreignTier = SubscriptionTier::create(['club_id' => $other->id, 'name' => 'Secret tier', 'annual_amount' => 999.00, 'is_active' => true]);
        $component = fn () => Livewire::test(SubscriptionIndex::class, ['clubSlug' => $this->club->slug]);

        $component()->call('assignTierToMember', $this->member1->id, $this->countryTier->id);
        $this->assertSame($this->countryTier->id, $this->member1->fresh()->subscription_tier_id);

        $component()->call('assignTierToMember', $this->member1->id, $foreignTier->id)->assertNotFound();
        $this->assertSame($this->countryTier->id, $this->member1->fresh()->subscription_tier_id, 'another lodge\'s tier is refused');

        $component()->call('assignTierToMember', $this->member1->id, null);
        $this->assertNull($this->member1->fresh()->subscription_tier_id);
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
