<?php

namespace Tests\Feature;

use App\Exceptions\LedgerRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\RecurringBillTemplate;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AccountingReports;
use App\Services\AccountingService;
use App\Services\FinancialYearService;
use App\Services\MeetingFinancialReturns;
use App\Services\RecurringBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bugs found by the end-to-end accounting run where money in the books and money on the documents drifted apart.
 */
class MoneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private AccountingService $accounting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = $this->makeLodge('Lodge of Unity', 'lodge-of-unity');
        $this->admin = User::factory()->create();
        $this->makeClubAdmin($this->admin, $this->club);
        $this->accounting = app(AccountingService::class);
    }

    private function makeLodge(string $name, string $slug): Club
    {
        $type = ClubType::firstOrCreate(['code' => 'lodge'], ['name' => 'Masonic Lodge', 'available_modules' => ['accounting']]);
        $club = Club::create(['name' => $name, 'slug' => $slug, 'club_type_id' => $type->id, 'is_active' => true]);
        app(AccountingService::class)->seedDefaultAccounts($club);

        return $club;
    }

    private function member(Club $club): User
    {
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        return $member;
    }

    private function raiseInvoice(Club $club, User $admin, float $amount = 120.00, array $extra = []): void
    {
        $this->actingAs($admin)->post(route('admin.accounting.invoices.store', $club->slug), $extra + [
            'user_id' => $this->member($club)->id,
            'title' => 'Dues',
            'amount' => $amount,
        ]);
    }

    public function test_each_lodge_numbers_its_own_invoices_and_a_deleted_number_is_not_reused_by_mistake(): void
    {
        $other = $this->makeLodge('Lodge of Peace', 'lodge-of-peace');
        $otherAdmin = User::factory()->create();
        $this->makeClubAdmin($otherAdmin, $other);

        $this->raiseInvoice($this->club, $this->admin);
        $this->raiseInvoice($other, $otherAdmin);
        $this->raiseInvoice($this->club, $this->admin);

        $this->assertSame(1, Invoice::where('club_id', $other->id)->count(), 'the second lodge can raise INV-…-0001 too');
        $this->assertSame(
            [date('Y').'-0001', date('Y').'-0002'],
            Invoice::where('club_id', $this->club->id)->orderBy('id')->pluck('invoice_number')->map(fn ($n) => substr($n, 4))->all(),
        );

        $first = Invoice::where('club_id', $this->club->id)->orderBy('id')->first();
        $this->actingAs($this->admin)->delete(route('admin.accounting.invoices.destroy', ['clubSlug' => $this->club->slug, 'id' => $first->id]));
        $this->raiseInvoice($this->club, $this->admin);

        $this->assertSame(2, Invoice::where('club_id', $this->club->id)->count());
        $this->assertSame(2, Invoice::where('club_id', $this->club->id)->distinct()->count('invoice_number'));
    }

    public function test_a_draft_or_pending_document_cannot_be_paid(): void
    {
        $bill = Bill::create(['club_id' => $this->club->id, 'bill_number' => 'BILL-1', 'vendor_name' => 'Hall', 'category' => 'Rent', 'amount' => 80, 'due_date' => now(), 'status' => 'pending_approval']);
        $invoice = Invoice::create(['club_id' => $this->club->id, 'user_id' => $this->member($this->club)->id, 'invoice_number' => 'INV-1', 'title' => 'Dues', 'amount' => 50, 'status' => 'draft']);

        foreach ([fn () => $this->accounting->markBillAsPaid($bill), fn () => $this->accounting->markInvoiceAsPaid($invoice)] as $pay) {
            try {
                $pay();
                $this->fail('An unapproved document must not be paid.');
            } catch (LedgerRuleException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame('pending_approval', $bill->fresh()->status);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame(0, JournalEntry::where('club_id', $this->club->id)->count());
    }

    public function test_the_amount_of_an_invoice_in_the_books_cannot_be_edited(): void
    {
        $this->raiseInvoice($this->club, $this->admin, 100);
        $invoice = Invoice::where('club_id', $this->club->id)->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.accounting.invoices.update', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]), [
            'user_id' => $invoice->user_id, 'title' => 'Dues', 'amount' => 500, 'status' => 'unpaid',
        ])->assertSessionHas('error');

        $this->assertEquals(100, $invoice->fresh()->amount);
    }

    public function test_a_closed_year_locks_its_invoices_and_leaves_nothing_half_saved(): void
    {
        $this->raiseInvoice($this->club, $this->admin, 100);
        $invoice = Invoice::where('club_id', $this->club->id)->firstOrFail();
        $year = $this->club->financialYearFor($invoice->created_at);
        $entry = JournalEntry::where('club_id', $this->club->id)->where('source_type', 'Invoice')->firstOrFail();
        app(FinancialYearService::class)->closeFinancialYear($this->club, $this->club->financialYearFor($entry->entry_date), $this->admin);

        $this->actingAs($this->admin)->delete(route('admin.accounting.invoices.destroy', ['clubSlug' => $this->club->slug, 'id' => $invoice->id]))
            ->assertSessionHas('error');
        $this->assertNotNull($invoice->fresh());

        $this->raiseInvoice($this->club, $this->admin, 70);
        $this->assertSame(1, Invoice::where('club_id', $this->club->id)->count(), 'a refused invoice leaves no unposted row');
        $this->assertNotNull($year);
    }

    public function test_an_opening_balance_is_set_not_added_and_keeps_its_sign(): void
    {
        $bank = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $balance = fn () => (float) $bank->fresh()->balance;

        $this->accounting->setOpeningBalance($this->club, $bank, 1000, '2026-05-01');
        $this->assertNull($this->accounting->setOpeningBalance($this->club, $bank, 1000, '2026-05-01'), 'the same figure again posts nothing');
        $this->assertEquals(1000, $balance());

        $this->accounting->setOpeningBalance($this->club, $bank, 1500, '2026-05-01');
        $this->assertEquals(1500, $balance(), '1500 after 1000 is 1500, not 2500');

        $this->accounting->setOpeningBalance($this->club, $bank, -250, '2026-05-01');
        $this->assertEquals(-250, $balance(), 'a negative figure is an overdrawn account');
    }

    public function test_a_financial_return_cannot_be_posted_twice(): void
    {
        $meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'Regular', 'meeting_date' => '2026-09-15', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Dark suits', 'status' => 'published']);
        $data = ['dining_fee_per_head' => 30, 'paid_diners_count' => 10, 'kitchen_cost_per_head' => 20, 'raffle_amount' => 15];
        $returns = app(MeetingFinancialReturns::class);

        $returns->postMeetingFinancialReturn($this->club, $meeting, $data);
        $entries = JournalEntry::where('club_id', $this->club->id)->count();

        $this->expectException(LedgerRuleException::class);
        try {
            $returns->postMeetingFinancialReturn($this->club, $meeting, $data);
        } finally {
            $this->assertSame($entries, JournalEntry::where('club_id', $this->club->id)->count());
            $this->assertSame(1, Bill::where('club_id', $this->club->id)->count());
        }
    }

    public function test_the_vat_return_counts_only_issued_documents(): void
    {
        $this->club->update(['settings' => ['vat' => ['enabled' => true, 'scheme' => 'standard']]]);
        $user = $this->member($this->club);
        $base = ['club_id' => $this->club->id, 'user_id' => $user->id, 'title' => 'Hire', 'amount' => 120, 'net_amount' => 100, 'vat_rate' => 20, 'vat_amount' => 20];
        Invoice::create($base + ['invoice_number' => 'INV-A', 'status' => 'unpaid']);
        Invoice::create($base + ['invoice_number' => 'INV-B', 'status' => 'draft']);
        Invoice::create($base + ['invoice_number' => 'INV-C', 'status' => 'pending_approval']);

        $vat = app(AccountingReports::class)->getVatReturnData($this->club);

        $this->assertEquals(20, $vat['output_vat']);
    }

    public function test_a_lodge_that_cannot_post_does_not_stop_the_other_lodges_recurring_bills(): void
    {
        $other = $this->makeLodge('Lodge of Peace', 'lodge-of-peace');
        foreach ([$this->club, $other] as $club) {
            RecurringBillTemplate::create(['club_id' => $club->id, 'vendor_name' => 'Insurer', 'category' => 'Insurance', 'amount' => 90, 'frequency' => 'monthly', 'next_run_date' => now()->subDay(), 'is_active' => true]);
        }
        app(FinancialYearService::class)->closeFinancialYear($this->club, $this->club->financialYearFor(now()), $this->admin);

        $result = app(RecurringBillService::class)->generateDueBills();

        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, Bill::where('club_id', $other->id)->count());
        $this->assertSame(0, Bill::where('club_id', $this->club->id)->count());
    }

    public function test_auditors_cannot_be_replaced_once_the_accounts_are_signed_off(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $three = User::factory()->create();
        $service = app(FinancialYearService::class);

        $service->signOffYearAudit($this->club, 2026, $one, $two);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $service->requestYearAudit($this->club, 2026, $two, $three);
        } finally {
            $this->assertSame($one->id, AccountingYearAudit::where('club_id', $this->club->id)->firstOrFail()->auditor_one_user_id);
        }
    }
}
