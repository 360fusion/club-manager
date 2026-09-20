<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftAidReliefChestReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $user;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members', 'charity'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->clubs()->attach($this->club->id, ['role' => 'admin']);

        $ledgerAcc = Account::create([
            'club_id' => $this->club->id,
            'code' => '1000',
            'name' => 'Main Operating Account',
            'type' => 'asset',
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::create([
            'club_id' => $this->club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'High Street Bank',
            'account_name' => 'Main Operating Account',
            'account_type' => 'current',
            'currency' => 'GBP',
            'opening_balance' => 1000.00,
            'is_active' => true,
        ]);

        FestivalTarget::create([
            'club_id' => $this->club->id,
            'festival_name' => 'Durham 2029 Festival',
            'relief_chest_ref' => 'E1418',
            'target_amount' => 10000.00,
            'bronze_tier' => 2500.00,
            'silver_tier' => 5000.00,
            'gold_tier' => 7500.00,
            'platinum_tier' => 10000.00,
        ]);
    }

    public function test_can_calculate_gift_aid_summary(): void
    {
        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::Envelope,
            'cash_amount' => 400.00,
            'cheque_amount' => 0.00,
            'is_gift_aid_eligible' => true,
            'gift_aid_status' => 'pending',
        ]);

        $service = new ReliefChestReconciliationService;
        $summary = $service->getGiftAidSummary($this->club);

        $this->assertEquals('E1418', $summary['relief_chest_ref']);
        $this->assertEquals(400.00, $summary['total_eligible_donations']);
        $this->assertEquals(100.00, $summary['pending_gift_aid_claim']); // 25% of 400
        $this->assertEquals(1, $summary['pending_claim_count']);
    }

    public function test_can_auto_reconcile_gift_aid_bank_transaction(): void
    {
        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::Envelope,
            'cash_amount' => 400.00,
            'cheque_amount' => 0.00,
            'is_gift_aid_eligible' => true,
            'gift_aid_status' => 'pending',
        ]);

        $bankImport = BankImport::create([
            'club_id' => $this->club->id,
            'bank_account_id' => $this->bankAccount->id,
            'filename' => 'statement.csv',
            'account_number' => '12345678',
            'sort_code' => '20-00-00',
            'total_lines' => 1,
            'total_amount' => 100.00,
        ]);

        $bankTx = BankTransaction::create([
            'club_id' => $this->club->id,
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => $bankImport->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'HMRC GIFT AID REPAYMENT',
            'raw_description' => 'HMRC GIFT AID REPAYMENT REF GA-100',
            'amount' => 100.00,
            'transaction_hash' => md5('HMRC GIFT AID REPAYMENT REF GA-100'.time()),
            'status' => BankTransactionStatus::Unmatched->value,
            'balance_after' => 1100.00,
        ]);

        $service = new ReliefChestReconciliationService;
        $result = $service->autoReconcileGiftAidAndReliefChest($this->club);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['reconciled_count']);
        $this->assertEquals(100.00, $result['total_amount']);

        $bankTx->refresh();
        $this->assertEquals(BankTransactionStatus::Matched->value, $bankTx->status->value);

        $this->assertDatabaseHas('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'source_type' => 'bank_transaction',
            'source_id' => $bankTx->id,
        ]);
    }

    public function test_can_generate_hmrc_gift_aid_csv_schedule(): void
    {
        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::Envelope,
            'cash_amount' => 200.00,
            'cheque_amount' => 0.00,
            'is_gift_aid_eligible' => true,
            'gift_aid_status' => 'pending',
        ]);

        $service = new ReliefChestReconciliationService;
        $csv = $service->generateHmrcGiftAidScheduleCsv($this->club);

        $this->assertStringContainsString('Relief Chest Ref,Collection Date,Collection Type', $csv);
        $this->assertStringContainsString('200.00', $csv);
        $this->assertStringContainsString('50.00', $csv); // 25% Gift Aid reclaim
        $this->assertStringContainsString('E1418', $csv);
    }
}
