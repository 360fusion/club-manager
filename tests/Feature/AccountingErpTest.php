<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\IndependentExaminerReport;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Invoice;
use App\Models\Province;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AnnualTreasurerReportService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingErpTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
    }

    public function test_seeds_default_chart_of_accounts_for_club(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $this->assertDatabaseHas('accounting_accounts', [
            'club_id' => $this->club->id,
            'code' => '1000',
            'name' => 'Operating Bank Account',
            'type' => 'asset',
        ]);

        $this->assertDatabaseHas('accounting_accounts', [
            'club_id' => $this->club->id,
            'code' => '4000',
            'name' => 'Membership Dues Income',
            'type' => 'revenue',
        ]);
    }

    public function test_posts_balanced_double_entry_journal(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $entry = $this->accountingService->postJournalEntry($this->club, [
            'description' => 'Annual Dues Payment from John Doe',
            'entry_date' => '2026-09-15',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 150.00, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 150.00],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(150.00, $entry->total_debit);
        $this->assertEquals(150.00, $entry->total_credit);

        // Verify account balances
        $this->assertEquals(150.00, $bankAcc->fresh()->balance);
        $this->assertEquals(150.00, $duesAcc->fresh()->balance);
    }

    public function test_throws_exception_on_unbalanced_journal_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->accountingService->seedDefaultAccounts($this->club);
        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $this->accountingService->postJournalEntry($this->club, [
            'description' => 'Unbalanced test',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 200.00, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 150.00], // Unbalanced by £50
            ],
        ]);
    }

    public function test_records_member_dues_and_event_ticket_sales_automatically(): void
    {
        $duesEntry = $this->accountingService->recordMemberDuesPayment(
            $this->club,
            250.00,
            'Member Annual Subscription Dues - Member #102'
        );
        $this->assertTrue($duesEntry->isBalanced());

        $ticketEntry = $this->accountingService->recordEventTicketSale(
            $this->club,
            75.00,
            'Regatta Dinner Ticket RSVP - Member #105'
        );
        $this->assertTrue($ticketEntry->isBalanced());

        $summary = $this->accountingService->getFinancialSummary($this->club);
        $this->assertEquals(325.00, $summary['total_assets']);
        $this->assertEquals(325.00, $summary['total_revenue']);
        $this->assertEquals(325.00, $summary['net_income']);
    }

    public function test_authenticated_admin_can_access_accounting_dashboard(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Accounting/Index')
            ->has('club')
            ->has('accounts')
            ->has('journalEntries')
            ->has('invoices')
            ->has('bills')
            ->has('summary')
        );
    }

    public function test_admin_can_create_custom_account(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.accounts.store', $this->club->slug), [
                'code' => '1500',
                'name' => 'Clubhouse Building & Grounds',
                'type' => 'asset',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('accounting_accounts', [
            'club_id' => $this->club->id,
            'code' => '1500',
            'name' => 'Clubhouse Building & Grounds',
            'type' => 'asset',
        ]);
    }

    public function test_admin_can_post_journal_entry_via_route(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $expenseAcc = Account::where('club_id', $this->club->id)->where('code', '5000')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.journal.store', $this->club->slug), [
                'description' => 'Repaired boat shed roof',
                'entry_date' => '2026-09-15',
                'items' => [
                    ['account_id' => $expenseAcc->id, 'debit' => 450.00, 'credit' => 0, 'memo' => 'Roof repair expense'],
                    ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => 450.00, 'memo' => 'Paid via bank transfer'],
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'description' => 'Repaired boat shed roof',
        ]);
    }

    public function test_admin_can_issue_member_invoice_and_auto_post_ledger(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.invoices.store', $this->club->slug), [
                'user_id' => $member->id,
                'title' => 'Locker Rental 2026',
                'amount' => 120.00,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'title' => 'Locker Rental 2026',
            'amount' => 120.00,
            'status' => 'unpaid',
        ]);

        $arAcc = Account::where('club_id', $this->club->id)->where('code', '1200')->firstOrFail();
        $this->assertEquals(120.00, $arAcc->balance);
    }

    public function test_admin_can_record_vendor_bill_and_pay_bill(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.store', $this->club->slug), [
                'vendor_name' => 'Boat Repair Supplies Ltd',
                'category' => 'Facility Maintenance',
                'amount' => 350.00,
                'due_date' => '2026-10-15',
                'notes' => 'Invoice #SUP-8812',
            ]);

        $response->assertRedirect();

        $bill = Bill::where('club_id', $this->club->id)->where('vendor_name', 'Boat Repair Supplies Ltd')->firstOrFail();
        $this->assertEquals('unpaid', $bill->status);
        $this->assertEquals(350.00, $bill->amount);

        $apAcc = Account::where('club_id', $this->club->id)->where('code', '2000')->firstOrFail();
        $this->assertEquals(350.00, $apAcc->balance);

        // Mark Bill as Paid
        $payResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.pay', ['clubSlug' => $this->club->slug, 'id' => $bill->id]));

        $payResponse->assertRedirect();
        $this->assertEquals('paid', $bill->fresh()->status);
        $this->assertEquals(0.00, $apAcc->fresh()->balance);
    }

    public function test_admin_can_view_create_and_edit_forms_for_accounting_items(): void
    {
        $this->actingAs($this->adminUser);
        $this->accountingService->seedDefaultAccounts($this->club);

        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'title' => 'Test Member Fee',
            'amount' => 50.00,
            'status' => 'draft',
            'invoice_number' => 'INV-TEST-1',
        ]);

        $bill = Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-TEST-1',
            'vendor_name' => 'Supplier Co',
            'category' => 'Utilities',
            'amount' => 100.00,
            'due_date' => '2026-10-01',
            'status' => 'draft',
        ]);

        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $journal = $this->accountingService->postJournalEntry($this->club, [
            'description' => 'Initial Capital',
            'entry_date' => '2026-09-01',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 500.00, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 500.00],
            ],
        ]);

        $this->get(route('admin.accounting.invoices.create', $this->club->slug))->assertOk();
        $this->get(route('admin.accounting.invoices.edit', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]))->assertOk();

        $this->get(route('admin.accounting.bills.create', $this->club->slug))->assertOk();
        $this->get(route('admin.accounting.bills.edit', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))->assertOk();

        $this->get(route('admin.accounting.journal.create', $this->club->slug))->assertOk();
        $this->get(route('admin.accounting.journal.edit', ['clubSlug' => $this->club->slug, 'id' => $journal->id]))->assertOk();
    }

    public function test_admin_can_update_and_publish_invoice(): void
    {
        $this->actingAs($this->adminUser);
        $this->accountingService->seedDefaultAccounts($this->club);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'title' => 'Draft Invoice',
            'amount' => 80.00,
            'status' => 'draft',
            'invoice_number' => 'INV-DRAFT-1',
        ]);

        $updateResponse = $this->put(route('admin.accounting.invoices.update', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]), [
            'user_id' => $member->id,
            'title' => 'Updated Invoice Title',
            'amount' => 95.00,
            'status' => 'draft',
        ]);
        $updateResponse->assertRedirect();
        $this->assertEquals('Updated Invoice Title', $invoice->fresh()->title);
        $this->assertEquals(95.00, $invoice->fresh()->amount);

        $publishResponse = $this->post(route('admin.accounting.invoices.publish', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]));
        $publishResponse->assertRedirect();
        $this->assertEquals('unpaid', $invoice->fresh()->status);
    }

    public function test_visiting_accounting_dashboard_does_not_seed_fabricated_data(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug));

        $response->assertStatus(200);

        $this->assertDatabaseCount('accounting_contacts', 0);
        $this->assertDatabaseCount('club_acc_bank_accounts', 0);

        $response->assertInertia(fn ($page) => $page
            ->where('settings.tax_registration_number', '')
            ->where('bankAccounts', [])
        );
    }

    public function test_onboarding_checklist_reflects_real_progress_and_can_be_dismissed(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug));

        $response->assertInertia(fn ($page) => $page
            ->where('onboarding.dismissed', false)
            ->where('onboarding.steps.0.key', 'bank_account')
            ->where('onboarding.steps.0.done', false)
            ->where('onboarding.steps.1.key', 'opening_balance')
            ->where('onboarding.steps.1.done', false)
            ->where('onboarding.steps.2.key', 'financial_year_end')
            ->where('onboarding.steps.2.done', false)
            ->where('onboarding.steps.3.key', 'vat_settings')
            ->where('onboarding.steps.3.done', false)
            ->where('onboarding.steps.3.skippable', true)
        );

        $ledgerAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        BankAccount::create([
            'club_id' => $this->club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'High Street Bank',
            'account_name' => 'Main Operating Account',
            'account_type' => 'current',
            'currency' => 'GBP',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $this->accountingService->setOpeningBalance($this->club, $ledgerAcc, 500.00);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.financial_year_end.update', $this->club->slug), ['financial_year_end_month' => 3])
            ->assertRedirect();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug));

        $response->assertInertia(fn ($page) => $page
            ->where('onboarding.steps.0.done', true)
            ->where('onboarding.steps.1.done', true)
            ->where('onboarding.steps.2.done', true)
            ->where('settings.financial_year_end_month', 3)
        );

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.onboarding.dismiss', $this->club->slug))
            ->assertRedirect();

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug))
            ->assertInertia(fn ($page) => $page->where('onboarding.dismissed', true));
    }

    public function test_comparative_income_expenditure_reports_zero_not_fabricated_figures(): void
    {
        $data = $this->accountingService->getComparativeIncomeExpenditureData($this->club);

        $this->assertEquals(0.0, $data['current_totals']['income']);
        $this->assertEquals(0.0, $data['current_totals']['expenditure']);
        $this->assertEquals(0.0, $data['prior_totals']['income']);
        $this->assertEquals(0.0, $data['prior_totals']['expenditure']);

        foreach ($data['rows'] as $row) {
            $this->assertEquals(0.0, $row['current_income']);
            $this->assertEquals(0.0, $row['current_expenditure']);
            $this->assertEquals(0.0, $row['prior_income']);
            $this->assertEquals(0.0, $row['prior_expenditure']);
        }
    }

    public function test_admin_can_delete_draft_invoice_and_bill(): void
    {
        $this->actingAs($this->adminUser);
        $this->accountingService->seedDefaultAccounts($this->club);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'title' => 'Invoice To Delete',
            'amount' => 40.00,
            'status' => 'draft',
            'invoice_number' => 'INV-DEL-1',
        ]);

        $bill = Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-DEL-1',
            'vendor_name' => 'Delete Vendor',
            'category' => 'Supplies',
            'amount' => 60.00,
            'due_date' => '2026-10-01',
            'status' => 'draft',
        ]);

        $this->delete(route('admin.accounting.invoices.destroy', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]))->assertRedirect();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);

        $this->delete(route('admin.accounting.bills.destroy', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))->assertRedirect();
        $this->assertDatabaseMissing('accounting_bills', ['id' => $bill->id]);
    }

    /**
     * Regression test for a CHECK constraint on accounting_bills.status that only
     * exists on Postgres (unpaid/paid/cancelled) — createVendorBill() writes 'draft'
     * into it, which violated that constraint before the 2026_09_27_000000 migration
     * dropped it. SQLite (used here) doesn't enforce the constraint at all, so this
     * test can't reproduce the bug itself; it must be verified manually against a
     * real Postgres database.
     */
    public function test_vendor_bill_can_be_created_as_draft_and_published(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Draft Supplies Ltd',
            'category' => 'Facility Maintenance',
            'amount' => 120.00,
            'due_date' => '2026-11-01',
            'is_draft' => true,
        ]);

        $this->assertEquals('draft', $bill->status);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.publish', ['clubSlug' => $this->club->slug, 'id' => $bill->id]));

        $response->assertRedirect();
        $this->assertEquals('unpaid', $bill->fresh()->status);
    }

    public function test_activity_log_returns_entity_history_in_order_and_is_forbidden_without_billing_role(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.store', $this->club->slug), [
                'vendor_name' => 'Activity Log Vendor',
                'category' => 'Facility Maintenance',
                'amount' => 200.00,
                'due_date' => '2026-11-15',
            ]);
        $response->assertRedirect();
        $bill = Bill::where('club_id', $this->club->id)->where('vendor_name', 'Activity Log Vendor')->firstOrFail();

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.pay', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))
            ->assertRedirect();

        $this->actingAs($this->adminUser)
            ->put(route('admin.accounting.bills.update', ['clubSlug' => $this->club->slug, 'id' => $bill->id]), [
                'vendor_name' => 'Activity Log Vendor (renamed)',
                'category' => 'Facility Maintenance',
                'amount' => 200.00,
                'due_date' => '2026-11-15',
                'status' => 'paid',
            ])
            ->assertRedirect();

        $activityResponse = $this->actingAs($this->adminUser)
            ->getJson(route('admin.accounting.activity_log', ['clubSlug' => $this->club->slug, 'entityType' => 'bill', 'entityId' => $bill->id]));

        $activityResponse->assertOk();
        $entries = $activityResponse->json('entries');
        $this->assertCount(2, $entries);
        $this->assertEquals('updated', $entries[0]['action']);
        $this->assertEquals('paid', $entries[1]['action']);
        $this->assertEquals($this->adminUser->name, $entries[0]['user_name']);

        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)
            ->getJson(route('admin.accounting.activity_log', ['clubSlug' => $this->club->slug, 'entityType' => 'bill', 'entityId' => $bill->id]))
            ->assertForbidden();
    }

    public function test_vat_is_off_by_default_and_never_touches_amounts(): void
    {
        $this->assertFalse($this->club->vatIsEnabled());
        $this->assertFalse($this->club->vatSettingsAreLocked());

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Non-VAT Supplier',
            'category' => 'Supplies',
            'amount' => 120.00,
            'due_date' => '2026-10-01',
        ]);

        $this->assertEquals(120.00, $bill->amount);
        $this->assertNull($bill->net_amount);
        $this->assertNull($bill->vat_amount);
        $this->assertFalse($bill->hasVat());
        $this->assertFalse($this->club->vatSettingsAreLocked());
    }

    public function test_enabling_vat_splits_vendor_bill_into_net_and_vat_control_account(): void
    {
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'vat' => ['enabled' => true, 'scheme' => 'standard', 'default_rate' => 20.00],
        ])]);
        $this->club->refresh();
        $this->assertTrue($this->club->vatIsEnabled());

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'VAT Registered Supplier',
            'category' => 'Supplies',
            'amount' => 120.00,
            'due_date' => '2026-10-01',
        ]);

        $this->assertEquals(120.00, $bill->amount);
        $this->assertEquals(100.00, $bill->net_amount);
        $this->assertEquals(20.00, $bill->vat_amount);
        $this->assertEquals(20.00, $bill->vat_rate);
        $this->assertTrue($bill->hasVat());

        // Input VAT is a debit against the (liability-typed) VAT control account, so it
        // nets negative here — it reduces what the club would owe HMRC overall.
        $vatAcc = Account::where('club_id', $this->club->id)->where('code', '2200')->firstOrFail();
        $this->assertEquals(-20.00, $vatAcc->fresh()->balance);

        $expenseAcc = Account::where('club_id', $this->club->id)->where('code', '5000')->firstOrFail();
        $this->assertEquals(100.00, $expenseAcc->fresh()->balance);

        $this->assertTrue($this->club->vatSettingsAreLocked());
    }

    public function test_enabling_vat_splits_member_invoice_into_net_and_vat_control_account(): void
    {
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'vat' => ['enabled' => true, 'scheme' => 'standard', 'default_rate' => 20.00],
        ])]);
        $this->club->refresh();

        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.invoices.store', $this->club->slug), [
                'user_id' => $member->id,
                'title' => 'VAT-Inclusive Locker Rental',
                'amount' => 120.00,
            ]);
        $response->assertRedirect();

        $invoice = Invoice::where('club_id', $this->club->id)->where('title', 'VAT-Inclusive Locker Rental')->firstOrFail();
        $this->assertEquals(120.00, $invoice->amount);
        $this->assertEquals(100.00, $invoice->net_amount);
        $this->assertEquals(20.00, $invoice->vat_amount);

        $vatAcc = Account::where('club_id', $this->club->id)->where('code', '2200')->firstOrFail();
        $this->assertEquals(20.00, $vatAcc->fresh()->balance);

        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();
        $this->assertEquals(100.00, $duesAcc->fresh()->balance);
    }

    public function test_admin_can_enable_vat_and_a_locked_scheme_cannot_be_changed(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.vat_settings.update', $this->club->slug), [
                'enabled' => true,
                'scheme' => 'standard',
                'default_rate' => 20.00,
            ]);
        $response->assertRedirect();

        $this->club->refresh();
        $this->assertTrue($this->club->vatIsEnabled());
        $this->assertEquals('standard', $this->club->vatSettings()['scheme']);

        $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Locking Supplier',
            'category' => 'Supplies',
            'amount' => 60.00,
            'due_date' => '2026-10-01',
        ]);
        $this->assertTrue($this->club->vatSettingsAreLocked());

        $lockedResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.vat_settings.update', $this->club->slug), [
                'enabled' => true,
                'scheme' => 'flat_rate',
                'flat_rate_percent' => 16.5,
                'default_rate' => 20.00,
            ]);
        $lockedResponse->assertSessionHasErrors('scheme');

        $this->club->refresh();
        $this->assertEquals('standard', $this->club->vatSettings()['scheme']);
    }

    public function test_vat_settings_update_is_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.vat_settings.update', $this->club->slug), [
                'enabled' => true,
                'scheme' => 'standard',
                'default_rate' => 20.00,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_export_reports_as_csv(): void
    {
        $this->accountingService->recordMemberDuesPayment($this->club, 100.00, 'Dues');

        foreach (['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'comparative_income_expenditure', 'budget_vs_actual'] as $report) {
            $response = $this->actingAs($this->adminUser)
                ->get(route('admin.accounting.reports.export', ['clubSlug' => $this->club->slug, 'report' => $report]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        }
    }

    public function test_exporting_an_unknown_report_returns_404(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.reports.export', ['clubSlug' => $this->club->slug, 'report' => 'not_a_real_report']))
            ->assertNotFound();
    }

    public function test_admin_can_export_reports_as_pdf(): void
    {
        $this->accountingService->recordMemberDuesPayment($this->club, 100.00, 'Dues');

        foreach (['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'budget_vs_actual'] as $report) {
            $response = $this->actingAs($this->adminUser)
                ->get(route('admin.accounting.reports.export_pdf', ['clubSlug' => $this->club->slug, 'report' => $report]));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_exporting_an_unknown_report_as_pdf_returns_404(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.reports.export_pdf', ['clubSlug' => $this->club->slug, 'report' => 'not_a_real_report']))
            ->assertNotFound();
    }

    public function test_comparative_income_expenditure_has_no_standalone_pdf_export(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.reports.export_pdf', ['clubSlug' => $this->club->slug, 'report' => 'comparative_income_expenditure']))
            ->assertNotFound();
    }

    public function test_examiner_role_can_view_but_not_change_the_accounting_records(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $examiner = User::factory()->create();
        $this->club->users()->attach($examiner->id, ['role' => 'examiner', 'status' => 'active']);

        $bill = Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-EXAM-1',
            'vendor_name' => 'Examiner Test Vendor',
            'category' => 'General Expense',
            'amount' => 75.00,
            'due_date' => '2026-11-01',
            'status' => 'unpaid',
        ]);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $invoice = Invoice::create([
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'title' => 'Examiner Test Invoice',
            'amount' => 40.00,
            'status' => 'unpaid',
            'invoice_number' => 'INV-EXAM-1',
        ]);

        // Explicitly reclassified GET routes: an examiner must see 200 on every one of them.
        $getUrls = [
            route('admin.accounting.index', $this->club->slug),
            route('admin.accounting.index', ['clubSlug' => $this->club->slug, 'tab' => 'purchases']),
            route('admin.accounting.index', ['clubSlug' => $this->club->slug, 'tab' => 'sales']),
            route('admin.accounting.bills.edit', ['clubSlug' => $this->club->slug, 'id' => $bill->id]),
            route('admin.accounting.invoices.edit', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]),
            route('admin.accounting.reports.export', ['clubSlug' => $this->club->slug, 'report' => 'account_summary']),
            route('admin.accounting.reports.export_pdf', ['clubSlug' => $this->club->slug, 'report' => 'account_summary']),
            route('admin.accounting.activity_log', ['clubSlug' => $this->club->slug, 'entityType' => 'bill', 'entityId' => $bill->id]),
            route('admin.accounting.vat_return.export', $this->club->slug),
            route('admin.accounting.treasurer_report.export_pdf', $this->club->slug),
            route('admin.accounting.treasurer_report.export_csv', $this->club->slug),
        ];

        foreach ($getUrls as $url) {
            $status = $this->actingAs($examiner)->get($url)->getStatusCode();
            $this->assertEquals(200, $status, $url);
        }

        // Every mutating accounting route: an examiner must be refused, regardless of
        // whether the referenced record exists — the capability check runs as route
        // middleware, before the controller ever looks the record up.
        $writeRoutes = [];
        foreach (Route::getRoutes() as $route) {
            $method = array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0] ?? null;
            if ($method && preg_match('#^\{clubSlug\}/admin/accounting#', $route->uri())) {
                $writeRoutes[] = [$method, $route->uri()];
            }
        }
        $this->assertNotEmpty($writeRoutes);

        $leaks = [];
        foreach ($writeRoutes as [$method, $uri]) {
            $path = preg_replace(['#\{clubSlug\}#', '#\{[^}]+\??\}#'], [$this->club->slug, '1'], $uri);
            $status = $this->actingAs($examiner)->call($method, '/'.$path)->getStatusCode();

            if ($status !== 403) {
                $leaks[] = $method.' '.$uri.' => '.$status;
            }
        }

        $this->assertSame([], $leaks, "Mutating accounting routes an examiner did not get 403 on:\n".implode("\n", $leaks));
    }

    public function test_approval_threshold_disabled_leaves_bill_creation_unchanged(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 10000.00,
            'due_date' => '2026-11-01',
        ]);

        $this->assertEquals('unpaid', $bill->status);
        $this->assertDatabaseHas('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $bill->id]);
    }

    public function test_bill_above_approval_threshold_is_held_pending_approval_with_no_journal_posted(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => '2026-11-01',
        ]);

        $this->assertEquals('pending_approval', $bill->status);
        $this->assertDatabaseMissing('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $bill->id]);

        // Below the threshold: unaffected.
        $smallBill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Small Spend Vendor',
            'category' => 'General Expense',
            'amount' => 100.00,
            'due_date' => '2026-11-01',
        ]);
        $this->assertEquals('unpaid', $smallBill->status);
        $this->assertDatabaseHas('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $smallBill->id]);
    }

    public function test_pending_approval_bill_can_be_approved_by_a_different_admin_and_posts_the_journal(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => '2026-11-01',
            'created_by' => $this->adminUser->id,
        ]);

        $approver = User::factory()->create();
        $this->makeClubAdmin($approver, $this->club, 'treasurer');

        $this->actingAs($approver)
            ->post(route('admin.accounting.bills.approve', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))
            ->assertRedirect();

        $bill->refresh();
        $this->assertEquals('unpaid', $bill->status);
        $this->assertDatabaseHas('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $bill->id]);
    }

    public function test_pending_approval_bill_cannot_be_approved_by_its_own_creator(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => '2026-11-01',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.approve', ['clubSlug' => $this->club->slug, 'id' => $bill->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $bill->refresh();
        $this->assertEquals('pending_approval', $bill->status);
        $this->assertDatabaseMissing('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $bill->id]);
    }

    public function test_publishing_a_draft_bill_above_threshold_holds_it_pending_approval_instead_of_bypassing_it(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => '2026-11-01',
            'is_draft' => true,
        ]);
        $this->assertEquals('draft', $bill->status);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.publish', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))
            ->assertRedirect();

        $bill->refresh();
        $this->assertEquals('pending_approval', $bill->status);
        $this->assertDatabaseMissing('accounting_journal_entries', ['source_type' => 'VendorBill', 'source_id' => $bill->id]);
    }

    public function test_invoice_above_approval_threshold_is_held_pending_approval_and_can_be_approved(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.invoices.store', $this->club->slug), [
                'user_id' => $member->id,
                'title' => 'Big Invoice',
                'amount' => 900.00,
            ]);
        $response->assertRedirect();

        $invoice = Invoice::where('club_id', $this->club->id)->where('title', 'Big Invoice')->firstOrFail();
        $this->assertEquals('pending_approval', $invoice->status);
        $this->assertDatabaseMissing('accounting_journal_entries', ['source_type' => 'Invoice', 'source_id' => $invoice->id]);

        $approver = User::factory()->create();
        $this->makeClubAdmin($approver, $this->club, 'treasurer');

        $this->actingAs($approver)
            ->post(route('admin.accounting.invoices.approve', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertDatabaseHas('accounting_journal_entries', ['source_type' => 'Invoice', 'source_id' => $invoice->id]);
    }

    public function test_pending_approval_bill_can_still_be_deleted(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => '2026-11-01',
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('admin.accounting.bills.destroy', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('accounting_bills', ['id' => $bill->id]);
    }

    public function test_pending_approval_bills_appear_in_aged_payables_with_a_distinct_status(): void
    {
        $this->accountingService->seedDefaultAccounts($this->club);
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'approval_threshold' => ['enabled' => true, 'amount' => 500.00],
        ])]);

        $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Big Spend Vendor',
            'category' => 'General Expense',
            'amount' => 750.00,
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $reports = $this->accountingService->getReportsData($this->club);

        $this->assertCount(1, $reports['aged_payables']['items']);
        $this->assertEquals('pending_approval', $reports['aged_payables']['items'][0]['status']);
        $this->assertEquals(750.00, $reports['aged_payables']['total']);
    }

    private function addLodgeMember(string $email, MembershipStatus $status = MembershipStatus::Active): Member
    {
        return Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Test',
            'last_name' => $email,
            'email' => $email,
            'masonic_rank' => 'Bro',
            'membership_status' => $status,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    public function test_provincial_due_worksheet_multiplies_active_members_by_the_province_rates(): void
    {
        $province = Province::create(['name' => 'Province of Test', 'code' => 'test', 'per_capita_rate' => 12.50, 'festival_contribution_rate' => 3.00]);
        $this->club->update(['province_id' => $province->id]);
        $this->addLodgeMember('a@example.com');
        $this->addLodgeMember('b@example.com');
        $this->addLodgeMember('gone@example.com', MembershipStatus::Resigned);

        $worksheet = $this->accountingService->getProvincialDueWorksheet($this->club->fresh());

        $this->assertSame(2, $worksheet['member_count']);
        $this->assertSame(25.0, $worksheet['per_capita_amount']);
        $this->assertSame(6.0, $worksheet['festival_amount']);
        $this->assertSame(31.0, $worksheet['total_due']);

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.reports.export', ['clubSlug' => $this->club->slug, 'report' => 'provincial_due']))
            ->assertOk();
        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.reports.export_pdf', ['clubSlug' => $this->club->slug, 'report' => 'provincial_due']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_provincial_due_worksheet_reports_rate_not_set_instead_of_zero(): void
    {
        $province = Province::create(['name' => 'Province of Test', 'code' => 'test']);
        $this->club->update(['province_id' => $province->id]);
        $this->addLodgeMember('a@example.com');

        $worksheet = $this->accountingService->getProvincialDueWorksheet($this->club->fresh());

        $this->assertNull($worksheet['per_capita_rate']);
        $this->assertNull($worksheet['per_capita_amount']);
        $this->assertNull($worksheet['festival_amount']);
        $this->assertNull($worksheet['total_due']);
    }

    public function test_provincial_due_worksheet_handles_a_lodge_with_no_province(): void
    {
        $worksheet = $this->accountingService->getProvincialDueWorksheet($this->club);

        $this->assertFalse($worksheet['has_province']);
        $this->assertNull($worksheet['total_due']);
    }

    public function test_independent_examiner_report_is_hidden_until_the_charity_fund_is_registered(): void
    {
        $examiner = User::factory()->create();
        $this->club->users()->attach($examiner->id, ['role' => 'examiner', 'status' => 'active']);

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.independent_examiner.export_pdf', $this->club->slug))
            ->assertNotFound();

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.independent_examiner.request', $this->club->slug), ['financial_year' => 2026, 'examiner_user_id' => $examiner->id])
            ->assertNotFound();

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.index', $this->club->slug))
            ->assertInertia(fn ($page) => $page->where('charityCommission.registered', false)->where('independentExaminer.report', null));
    }

    public function test_independent_examiner_signs_once_and_the_report_is_stamped_and_renders_with_the_signature(): void
    {
        Mail::fake();
        $this->accountingService->seedDefaultAccounts($this->club);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.charity_commission.update', $this->club->slug), ['registered' => true, 'charity_number' => '1234567'])
            ->assertRedirect();

        $examiner = User::factory()->create(['name' => 'Erica Examiner']);
        $this->club->users()->attach($examiner->id, ['role' => 'examiner', 'status' => 'active']);
        $notExaminer = User::factory()->create();
        $this->club->users()->attach($notExaminer->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.independent_examiner.request', $this->club->slug), ['financial_year' => 2026, 'examiner_user_id' => $notExaminer->id])
            ->assertSessionHasErrors('examiner_user_id');

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.independent_examiner.request', $this->club->slug), ['financial_year' => 2026, 'examiner_user_id' => $examiner->id])
            ->assertSessionHasNoErrors();

        $report = IndependentExaminerReport::where('club_id', $this->club->id)->where('financial_year', 2026)->firstOrFail();
        $this->assertNull($report->examined_at);

        $signatures = app(SignatureRequestService::class);
        $pending = $signatures->forSignable($report)->where('purpose', 'independent_examiner_report')->first();
        $this->assertNotNull($pending);
        $signatures->sign($pending, 'typed', ['typed_name' => 'Erica Examiner'], '1.1.1.1', 'Agent');

        $this->assertNotNull($report->fresh()->examined_at);

        // The examiner role can download the signed report, but a plain member cannot.
        $this->actingAs($examiner)
            ->get(route('admin.accounting.independent_examiner.export_pdf', ['clubSlug' => $this->club->slug, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($notExaminer)
            ->get(route('admin.accounting.independent_examiner.export_pdf', ['clubSlug' => $this->club->slug, 'year' => 2026]))
            ->assertForbidden();

        $data = app(AnnualTreasurerReportService::class)->independentExaminerReportData($this->club->fresh(), 2026);
        $this->assertSame('Erica Examiner', $data['examiner_name']);
        $this->assertSame(['method' => 'typed', 'value' => 'Erica Examiner'], $data['signature']);
        $this->assertSame('1234567', $data['charity_number']);
    }
}
