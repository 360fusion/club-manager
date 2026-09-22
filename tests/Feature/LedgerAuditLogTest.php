<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\LedgerAuditLog;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LedgerAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Concord',
            'slug' => 'lodge-of-concord',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
        $this->accountingService->seedDefaultAccounts($this->club);
    }

    public function test_posting_a_journal_entry_writes_an_audit_log_entry(): void
    {
        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $entry = $this->accountingService->postJournalEntry($this->club, [
            'description' => 'Annual dues',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $this->assertDatabaseHas('accounting_ledger_audit_log', [
            'club_id' => $this->club->id,
            'entity_type' => 'journal_entry',
            'entity_id' => $entry->id,
            'action' => 'created',
        ]);
    }

    public function test_marking_a_bill_paid_logs_who_did_it(): void
    {
        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Test Vendor',
            'category' => 'Supplies',
            'amount' => 200.00,
            'due_date' => '2026-10-01',
        ]);

        $this->accountingService->markBillAsPaid($bill, $this->adminUser);

        $this->assertDatabaseHas('accounting_ledger_audit_log', [
            'club_id' => $this->club->id,
            'entity_type' => 'bill',
            'entity_id' => $bill->id,
            'action' => 'paid',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_deleting_a_bill_voids_rather_than_erases_its_ledger_entry_and_stops_it_affecting_balances(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.bills.store', $this->club->slug), [
                'vendor_name' => 'Doomed Vendor',
                'category' => 'Supplies',
                'amount' => 75.00,
                'due_date' => '2026-10-01',
            ]);

        $bill = Bill::where('club_id', $this->club->id)->where('vendor_name', 'Doomed Vendor')->firstOrFail();
        $apAcc = Account::where('club_id', $this->club->id)->where('code', '2000')->firstOrFail();
        $this->assertEquals(75.00, $apAcc->fresh()->balance);

        $this->actingAs($this->adminUser)
            ->delete(route('admin.accounting.bills.destroy', ['clubSlug' => $this->club->slug, 'id' => $bill->id]))
            ->assertRedirect();

        // The journal entry is voided, not hard-deleted, and no longer affects the balance.
        $this->assertDatabaseHas('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'source_type' => 'VendorBill',
            'source_id' => $bill->id,
            'status' => 'void',
        ]);
        $this->assertEquals(0.00, $apAcc->fresh()->balance);

        $this->assertDatabaseHas('accounting_ledger_audit_log', [
            'club_id' => $this->club->id,
            'entity_type' => 'bill',
            'entity_id' => $bill->id,
            'action' => 'voided',
        ]);
    }

    public function test_audit_log_entries_cannot_be_edited_or_deleted(): void
    {
        $log = LedgerAuditLog::create([
            'club_id' => $this->club->id,
            'entity_type' => 'bill',
            'entity_id' => 1,
            'action' => 'created',
            'summary' => 'Test entry',
        ]);

        $this->expectException(LogicException::class);
        $log->update(['summary' => 'Tampered']);
    }

    public function test_audit_log_entries_cannot_be_deleted(): void
    {
        $log = LedgerAuditLog::create([
            'club_id' => $this->club->id,
            'entity_type' => 'bill',
            'entity_id' => 1,
            'action' => 'created',
            'summary' => 'Test entry',
        ]);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
