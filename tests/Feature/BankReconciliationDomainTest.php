<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankReconciliationDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;
    protected User $adminUser;
    protected Member $member;
    protected MemberSubscription $subscription;
    protected Bill $supplierBill;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members', 'banking'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->clubs()->attach($this->club->id, ['role' => 'admin']);

        $tier = SubscriptionTier::create([
            'club_id' => $this->club->id,
            'name' => 'Full Subscribing Member',
            'annual_amount' => 160.00,
            'is_active' => true,
        ]);

        $this->member = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Winston',
            'last_name' => 'Churchill',
            'email' => 'winston@example.com',
            'masonic_rank' => 'WBro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
            'subscription_tier_id' => $tier->id,
        ]);

        $this->subscription = MemberSubscription::create([
            'club_id' => $this->club->id,
            'member_id' => $this->member->id,
            'tier_id' => $tier->id,
            'billing_year' => 2026,
            'due_date' => Carbon::parse('2026-04-01'),
            'amount_due' => 160.00,
            'amount_paid' => 0.00,
            'status' => SubscriptionStatus::Unpaid,
            'invoice_reference' => 'INV-2026-M0042',
        ]);

        $this->supplierBill = Bill::create([
            'club_id' => $this->club->id,
            'vendor_name' => 'Grand Hall Catering Ltd',
            'bill_number' => 'BILL-9001',
            'amount' => 350.00,
            'status' => 'unpaid',
            'category' => 'Catering & Festive Board',
            'due_date' => Carbon::now()->addDays(14),
        ]);
    }

    public function test_suggest_matches_for_member_dues_credit(): void
    {
        $import = BankImport::create([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'total_lines' => 1,
            'total_amount' => 160.00,
        ]);

        $tx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_import_id' => $import->id,
            'transaction_date' => Carbon::parse('2026-09-15'),
            'raw_description' => 'BACS Dues Churchill INV-2026-M0042',
            'amount' => 160.00,
            'transaction_hash' => 'hash001',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService());
        $suggestions = $matcher->suggestMatches($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertEquals('member_subscription', $suggestions[0]['match_type']);
        $this->assertEquals($this->subscription->id, $suggestions[0]['target_id']);
        $this->assertEquals(100, $suggestions[0]['confidence_score']);
        $this->assertEquals('high', $suggestions[0]['confidence_level']);
    }

    public function test_suggest_matches_for_supplier_bill_debit(): void
    {
        $import = BankImport::create([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'total_lines' => 1,
            'total_amount' => -350.00,
        ]);

        $tx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_import_id' => $import->id,
            'transaction_date' => Carbon::parse('2026-09-16'),
            'raw_description' => 'Payment to Grand Hall Catering Ltd BILL-9001',
            'amount' => -350.00,
            'transaction_hash' => 'hash002',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService());
        $suggestions = $matcher->suggestMatches($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertEquals('supplier_bill', $suggestions[0]['match_type']);
        $this->assertEquals($this->supplierBill->id, $suggestions[0]['target_id']);
        $this->assertEquals(100, $suggestions[0]['confidence_score']);
    }

    public function test_reconcile_transaction_updates_downstream_subscription_and_transaction(): void
    {
        $import = BankImport::create([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'total_lines' => 1,
            'total_amount' => 160.00,
        ]);

        $tx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_import_id' => $import->id,
            'transaction_date' => Carbon::parse('2026-09-15'),
            'raw_description' => 'Dues Churchill INV-2026-M0042',
            'amount' => 160.00,
            'transaction_hash' => 'hash003',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService());
        $result = $matcher->reconcileTransaction($tx, 'member_subscription', $this->subscription->id);

        $this->assertTrue($result);

        $tx->refresh();
        $this->assertEquals(BankTransactionStatus::Matched, $tx->status);

        $this->subscription->refresh();
        $this->assertEquals(160.00, $this->subscription->amount_paid);
        $this->assertEquals(SubscriptionStatus::Paid, $this->subscription->status);
    }

    public function test_reconciliation_workspace_livewire_component(): void
    {
        $import = BankImport::create([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'total_lines' => 1,
            'total_amount' => 160.00,
        ]);

        $tx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_import_id' => $import->id,
            'transaction_date' => Carbon::parse('2026-09-15'),
            'raw_description' => 'Dues Churchill INV-2026-M0042',
            'amount' => 160.00,
            'transaction_hash' => 'hash004',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(BankReconciliationWorkspace::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200)
            ->assertSee('Bank Reconciliation Workspace')
            ->assertSee('Dues Churchill INV-2026-M0042')
            ->call('reconcileSuggested', $tx->id, 'member_subscription', $this->subscription->id)
            ->assertHasNoErrors();

        $tx->refresh();
        $this->assertEquals(BankTransactionStatus::Matched, $tx->status);
    }
}
