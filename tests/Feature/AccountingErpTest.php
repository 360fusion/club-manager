<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
