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
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountingService;
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

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
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

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
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

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
        $result = $matcher->reconcileTransaction($tx, 'member_subscription', $this->subscription->id);

        $this->assertTrue($result);

        $tx->refresh();
        $this->assertEquals(BankTransactionStatus::Matched, $tx->status);

        $this->subscription->refresh();
        $this->assertEquals(160.00, $this->subscription->amount_paid);
        $this->assertEquals(SubscriptionStatus::Paid, $this->subscription->status);
    }

    public function test_reconciling_a_supplier_bill_marks_it_paid_and_reconciled_independently(): void
    {
        app(AccountingService::class)->seedDefaultAccounts($this->club);

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
            'transaction_hash' => 'hash-supplier-bill',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
        $matcher->reconcileTransaction($tx, 'supplier_bill', $this->supplierBill->id);

        $this->supplierBill->refresh();
        $this->assertEquals('paid', $this->supplierBill->status);
        $this->assertNotNull($this->supplierBill->paid_at);
        $this->assertNotNull($this->supplierBill->reconciled_at);
        $this->assertEquals($tx->id, $this->supplierBill->reconciled_bank_transaction_id);

        // The correct Accounts Payable clearing entry was posted via markBillAsPaid()
        // (source_type VendorBillPayment) — not a second, generic bank_transaction
        // entry against a hardcoded offset account.
        $this->assertDatabaseHas('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'source_type' => 'VendorBillPayment',
            'source_id' => $this->supplierBill->id,
        ]);
        $this->assertDatabaseMissing('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'source_type' => 'bank_transaction',
            'source_id' => $tx->id,
        ]);
    }

    public function test_marking_a_bill_paid_manually_then_reconciling_does_not_double_post_the_ledger(): void
    {
        $accountingService = app(AccountingService::class);
        $accountingService->seedDefaultAccounts($this->club);
        $accountingService->markBillAsPaid($this->supplierBill, $this->adminUser);

        $this->supplierBill->refresh();
        $this->assertEquals('paid', $this->supplierBill->status);
        $this->assertNull($this->supplierBill->reconciled_at);
        $this->assertDatabaseCount('accounting_journal_entries', 1);

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
            'transaction_hash' => 'hash-already-paid',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
        $matcher->reconcileTransaction($tx, 'supplier_bill', $this->supplierBill->id);

        $this->supplierBill->refresh();
        $this->assertNotNull($this->supplierBill->reconciled_at);
        $this->assertEquals($tx->id, $this->supplierBill->reconciled_bank_transaction_id);

        // Reconciling an already-paid bill must not post a second settlement entry.
        $this->assertDatabaseCount('accounting_journal_entries', 1);
    }

    public function test_suggest_matches_and_reconcile_for_unpaid_invoice_credit(): void
    {
        app(AccountingService::class)->seedDefaultAccounts($this->club);

        $payer = User::factory()->create(['name' => 'Alice Example']);
        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $payer->id,
            'title' => 'Locker Rental 2026',
            'amount' => 120.00,
            'status' => 'unpaid',
            'invoice_number' => 'INV-LOCKER-1',
        ]);

        $import = BankImport::create([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'total_lines' => 1,
            'total_amount' => 120.00,
        ]);

        $tx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_import_id' => $import->id,
            'transaction_date' => Carbon::parse('2026-09-17'),
            'raw_description' => 'BACS INV-LOCKER-1 Alice Example',
            'amount' => 120.00,
            'transaction_hash' => 'hash-invoice',
            'status' => BankTransactionStatus::Unmatched,
        ]);

        $matcher = new BankReconciliationMatcherService(new SubscriptionBillingService);
        $suggestions = $matcher->suggestMatches($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertEquals('invoice', $suggestions[0]['match_type']);
        $this->assertEquals($invoice->id, $suggestions[0]['target_id']);

        $matcher->reconcileTransaction($tx, 'invoice', $invoice->id);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertNotNull($invoice->reconciled_at);
        $this->assertEquals($tx->id, $invoice->reconciled_bank_transaction_id);
    }

    public function test_mark_paid_actions_are_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.bills.pay', ['clubSlug' => $this->club->slug, 'id' => $this->supplierBill->id]))
            ->assertForbidden();

        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Test Fee',
            'amount' => 50.00,
            'status' => 'unpaid',
            'invoice_number' => 'INV-FORBID-1',
        ]);

        $this->actingAs($member)
            ->post(route('admin.accounting.invoices.pay', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]))
            ->assertForbidden();
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
