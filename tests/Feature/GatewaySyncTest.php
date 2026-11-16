<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\GoCardlessSyncService;
use App\Domains\ClubAccounting\Services\PayPalSyncService;
use App\Domains\ClubAccounting\Services\StripeSyncService;
use App\Domains\ClubAccounting\Services\SumUpSyncService;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Lines fetched from payment providers are saved as bank lines (they once never were: the columns were wrong), and a
 * second fetch of the same lines adds nothing.
 */
class GatewaySyncTest extends TestCase
{
    use RefreshDatabase;

    private function account(array $credentials): BankAccount
    {
        return BankAccount::create(['club_id' => Club::factory()->create()->id, 'bank_name' => 'Provider', 'account_name' => 'Takings'] + $credentials);
    }

    private function assertSavedOnce(BankAccount $account, callable $sync, string $reference, float $amount): void
    {
        $first = $sync();
        $second = $sync();

        $this->assertTrue($first['success'] ?? false, json_encode($first));
        $line = BankTransaction::where('bank_account_id', $account->id)->sole();
        $this->assertSame($reference, $line->reference);
        $this->assertEquals($amount, $line->amount);
        $this->assertNotEmpty($line->raw_description);
        $this->assertSame(1, $second['skipped_count']);
    }

    public function test_stripe_balance_transactions_are_saved(): void
    {
        Http::fake(['api.stripe.com/v1/balance_transactions*' => Http::response(['data' => [['id' => 'txn_1', 'amount' => 3000, 'fee' => 70, 'net' => 2930, 'type' => 'charge', 'created' => 1790000000]]])]);
        $account = $this->account(['stripe_secret_key' => 'sk_test_x']);

        $this->assertSavedOnce($account, fn () => app(StripeSyncService::class)->syncTransactions($account->fresh()), 'txn_1', 29.30);
    }

    public function test_sumup_transactions_are_saved(): void
    {
        Http::fake(['api.sumup.com/*' => Http::response(['items' => [['transaction_code' => 'SU1', 'amount' => 20, 'fee_amount' => 0.5, 'timestamp' => '2026-10-01T10:00:00Z']]])]);
        $account = $this->account(['sumup_api_key' => 'key']);

        $this->assertSavedOnce($account, fn () => app(SumUpSyncService::class)->syncTransactions($account->fresh()), 'SU1', 19.50);
    }

    public function test_paypal_transactions_are_saved(): void
    {
        Http::fake([
            'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api-m.paypal.com/v1/reporting/transactions*' => Http::response(['transaction_details' => [['transaction_info' => ['transaction_id' => 'PP1', 'transaction_amount' => ['value' => '50.00'], 'fee_amount' => ['value' => '-1.50'], 'transaction_initiation_date' => '2026-10-01T10:00:00Z'], 'payer_info' => ['email_address' => 'payer@example.test']]]]),
        ]);
        $account = $this->account(['paypal_client_id' => 'id', 'paypal_client_secret' => 'secret', 'paypal_environment' => 'live']);

        $this->assertSavedOnce($account, fn () => app(PayPalSyncService::class)->syncTransactions($account->fresh()), 'PP1', 48.50);
    }

    public function test_gocardless_payments_are_saved(): void
    {
        Http::fake(['api-sandbox.gocardless.com/payments*' => Http::response(['payments' => [['id' => 'PM1', 'amount' => 1500, 'status' => 'paid_out', 'charge_date' => '2026-10-01']]])]);
        $account = $this->account(['gocardless_access_token' => 'tok']);

        $this->assertSavedOnce($account, fn () => app(GoCardlessSyncService::class)->syncTransactions($account->fresh()), 'PM1', 15.00);
    }
}
