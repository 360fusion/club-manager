<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Models\Accounting\Budget;
use App\Models\Club;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberAccountsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->treasurer = $this->person('treasurer');
        $accounting = app(AccountingService::class);
        $ledger = $accounting->getAccount($this->club, '1000');
        $this->bank = BankAccount::create(['club_id' => $this->club->id, 'account_id' => $ledger->id, 'bank_name' => 'Lloyds', 'account_name' => 'Lodge current account', 'account_type' => 'current', 'is_active' => true]);

        $this->actingAs($this->treasurer);
        $accounting->postJournalEntry($this->club, ['entry_date' => now()->toDateString(), 'description' => 'Dues', 'items' => [
            ['account_id' => $ledger->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $accounting->getAccount($this->club, '4000')->id, 'debit' => 0, 'credit' => 500],
        ]]);
        Budget::create(['club_id' => $this->club->id, 'financial_year' => $this->club->financialYearFor(now()), 'account_id' => $accounting->getAccount($this->club, '4000')->id, 'budgeted_amount' => 1000]);
    }

    private function person(string $role, ?string $office = null): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);
        if ($office) {
            Member::create(['club_id' => $this->club->id, 'user_id' => $user->id, 'first_name' => 'Tom', 'last_name' => 'Treasurer', 'membership_status' => 'active', 'current_office' => $office]);
        }

        return $user;
    }

    private function statement(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n01/11/2026,ALMS PAID IN,80.00\n02/11/2026,CATERER INVOICE,-120.00\n");
    }

    public function test_the_overview_shows_the_bank_this_years_money_against_the_budget_and_what_needs_doing(): void
    {
        BankTransaction::create(['club_id' => $this->club->id, 'bank_account_id' => $this->bank->id, 'bank_import_id' => BankImport::create(['club_id' => $this->club->id, 'filename' => 'a.csv', 'total_lines' => 1, 'total_amount' => 5])->id, 'transaction_date' => now()->subDays(3)->toDateString(), 'raw_description' => 'x', 'amount' => 5, 'balance_after' => 505, 'transaction_hash' => 'h1', 'status' => BankTransactionStatus::Unmatched]);

        $this->get(route('member.accounts', ['slug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->component('Member/Accounts/Overview')
            ->where('overview.bank.0.name', 'Lodge current account')
            ->where('overview.bank.0.balance', '£500.00')
            ->where('overview.bank.0.statement_balance', '£505.00')
            ->where('overview.bank.0.days_old', 3)
            ->where('overview.income.rows.0.amount', '£500.00')
            ->where('overview.income.rows.0.budget', '£1,000.00')
            ->where('overview.income.rows.0.share', 50)
            ->where('overview.todo.unreconciled', 1)
            ->where('canImport', true));
    }

    public function test_the_treasurer_office_can_see_and_import_an_examiner_can_only_see_and_a_member_neither(): void
    {
        $office = $this->person('member', 'treasurer');
        $this->actingAs($office)->get(route('member.accounts', ['slug' => $this->club->slug]))->assertOk();
        $this->actingAs($office)->get(route('member.accounts.import', ['slug' => $this->club->slug]))->assertOk();

        $examiner = $this->person('examiner');
        $this->actingAs($examiner)->get(route('member.accounts', ['slug' => $this->club->slug]))->assertInertia(fn ($page) => $page->where('canImport', false));
        $this->actingAs($examiner)->get(route('member.accounts.import', ['slug' => $this->club->slug]))->assertForbidden();

        $member = $this->person('member');
        $this->actingAs($member)->get(route('member.accounts', ['slug' => $this->club->slug]))->assertForbidden();
    }

    public function test_a_statement_is_shown_before_it_is_imported_and_lines_already_imported_are_skipped(): void
    {
        $hash = app(BankStatementParserService::class)->generateTransactionHash('2026-11-01', 80.00, 'ALMS PAID IN');
        BankTransaction::create(['club_id' => $this->club->id, 'bank_account_id' => $this->bank->id, 'bank_import_id' => BankImport::create(['club_id' => $this->club->id, 'filename' => 'old.csv', 'total_lines' => 1, 'total_amount' => 80])->id, 'transaction_date' => '2026-11-01', 'raw_description' => 'ALMS PAID IN', 'amount' => 80, 'transaction_hash' => $hash, 'status' => BankTransactionStatus::Unmatched]);

        $redirect = $this->post(route('member.accounts.import.preview', ['slug' => $this->club->slug]), ['statement_file' => $this->statement()])->assertRedirect();
        $this->assertSame(1, BankTransaction::count(), 'nothing is imported by reading the file');

        $token = null;
        $this->get($redirect->headers->get('Location'))->assertInertia(function ($page) use (&$token) {
            $token = $page->toArray()['props']['preview']['token'];

            return $page->where('preview.new_count', 1)->where('preview.duplicate_count', 1)->where('preview.money_out', '£120.00')->where('preview.lines.0.duplicate', true);
        });

        $this->post(route('member.accounts.import.store', ['slug' => $this->club->slug]), ['token' => $token])->assertRedirect(route('member.accounts.match', ['slug' => $this->club->slug]))->assertSessionHas('success', '1 new line imported.');
        $this->assertSame(2, BankTransaction::count());
        $this->assertSame($this->bank->id, BankTransaction::latest('id')->first()->bank_account_id);

        $this->post(route('member.accounts.import.store', ['slug' => $this->club->slug]), ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_a_file_that_is_not_a_statement_is_refused(): void
    {
        $this->post(route('member.accounts.import.preview', ['slug' => $this->club->slug]), ['statement_file' => UploadedFile::fake()->createWithContent('notes.txt', "hello\nworld\n")])
            ->assertSessionHasErrors('statement_file');
    }
}
