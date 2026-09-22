<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected BudgetService $budgetService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Harmony',
            'slug' => 'lodge-of-harmony',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
        $this->budgetService = app(BudgetService::class);
        $this->accountingService->seedDefaultAccounts($this->club);
    }

    public function test_budget_vs_actual_computes_variance_for_accounts_with_budget_or_activity(): void
    {
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();
        $expenseAcc = Account::where('club_id', $this->club->id)->where('code', '5000')->firstOrFail();
        $untouchedAcc = Account::where('club_id', $this->club->id)->where('code', '5100')->firstOrFail();

        $this->budgetService->setBudgetLines($this->club, 2026, [
            ['account_id' => $duesAcc->id, 'budgeted_amount' => 1000.00],
            ['account_id' => $expenseAcc->id, 'budgeted_amount' => 500.00],
        ]);

        // Actual dues income of 800 (under budget) and actual expenses of 650 (over budget).
        $this->accountingService->recordMemberDuesPayment($this->club, 800.00, 'Dues');
        $this->accountingService->postJournalEntry($this->club, [
            'description' => 'Hall hire',
            'entry_date' => '2026-06-01',
            'items' => [
                ['account_id' => $expenseAcc->id, 'debit' => 650.00, 'credit' => 0],
                ['account_id' => $this->accountingService->getAccount($this->club, '1000')->id, 'debit' => 0, 'credit' => 650.00],
            ],
        ]);

        $result = $this->budgetService->getBudgetVsActual($this->club, 2026);

        $duesRow = collect($result['rows'])->firstWhere('code', '4000');
        $this->assertEquals(1000.00, $duesRow['budgeted']);
        $this->assertEquals(800.00, $duesRow['actual']);
        $this->assertEquals(-200.00, $duesRow['variance']);
        $this->assertEquals(-20.0, $duesRow['variance_pct']);

        $expenseRow = collect($result['rows'])->firstWhere('code', '5000');
        $this->assertEquals(500.00, $expenseRow['budgeted']);
        $this->assertEquals(650.00, $expenseRow['actual']);
        $this->assertEquals(150.00, $expenseRow['variance']);

        // An account with neither a budget nor activity is left out entirely.
        $this->assertNull(collect($result['rows'])->firstWhere('code', '5100'));
        $this->assertNotNull($untouchedAcc);
    }

    public function test_saving_a_budget_via_the_route_persists_lines_and_is_forbidden_for_members(): void
    {
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.budget.update', $this->club->slug), [
                'financial_year' => 2026,
                'lines' => [
                    ['account_id' => $duesAcc->id, 'budgeted_amount' => 2000.00],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounting_budgets', [
            'club_id' => $this->club->id,
            'financial_year' => 2026,
            'account_id' => $duesAcc->id,
            'budgeted_amount' => 2000.00,
        ]);

        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.budget.update', $this->club->slug), [
                'financial_year' => 2026,
                'lines' => [['account_id' => $duesAcc->id, 'budgeted_amount' => 1.00]],
            ])
            ->assertForbidden();
    }
}
