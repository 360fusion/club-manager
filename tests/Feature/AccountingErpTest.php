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
        $this->club->users()->attach($this->adminUser->id, ['role' => 'admin', 'status' => 'approved']);

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
}
