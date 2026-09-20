<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use Exception;
use Illuminate\Support\Facades\Http;

class GoCardlessSyncService
{
    /**
     * Get API base URL for GoCardless environment.
     */
    protected function getBaseUrl(string $environment): string
    {
        return strtolower($environment) === 'live'
            ? 'https://api.gocardless.com'
            : 'https://api-sandbox.gocardless.com';
    }

    /**
     * Test API connection to GoCardless using Access Token.
     */
    public function testConnection(string $accessToken, string $environment = 'sandbox'): array
    {
        try {
            $baseUrl = $this->getBaseUrl($environment);
            $response = Http::withHeaders([
                'GoCardless-Version' => '2015-07-06',
            ])->withToken(trim($accessToken))
                ->get("{$baseUrl}/creditors");

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();

                return ['success' => false, 'message' => "GoCardless Error: {$error}"];
            }

            $creditors = $response->json('creditors') ?? [];
            $creditorName = ! empty($creditors) ? ($creditors[0]['name'] ?? 'Creditor') : 'Merchant';

            return [
                'success' => true,
                'message' => "Successfully connected to GoCardless ({$environment}) as '{$creditorName}'!",
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync settled GoCardless transactions & payouts for a BankAccount.
     */
    public function syncTransactions(BankAccount $bankAccount, ?string $startDate = null, ?string $endDate = null): array
    {
        if (! $bankAccount->gocardless_access_token) {
            throw new Exception('GoCardless Access Token is not configured for this account.');
        }

        $accessToken = trim($bankAccount->gocardless_access_token);
        $environment = $bankAccount->gocardless_environment ?? 'sandbox';
        $baseUrl = $this->getBaseUrl($environment);

        $params = [
            'limit' => 100,
        ];

        if ($startDate) {
            $params['created_at[gte]'] = date('Y-m-d\TH:i:s\Z', strtotime($startDate));
        } elseif ($bankAccount->last_synced_at) {
            $params['created_at[gte]'] = date('Y-m-d\TH:i:s\Z', strtotime('-1 day', $bankAccount->last_synced_at->timestamp));
        } else {
            $params['created_at[gte]'] = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        }

        if ($endDate) {
            $params['created_at[lte]'] = date('Y-m-d\TH:i:s\Z', strtotime($endDate.' 23:59:59'));
        }

        $response = Http::withHeaders([
            'GoCardless-Version' => '2015-07-06',
        ])->withToken($accessToken)
            ->get("{$baseUrl}/payments", $params);

        if ($response->failed()) {
            $bankAccount->update(['sync_status' => 'error']);
            $errorMsg = $response->json('error.message') ?? $response->body();
            throw new Exception("GoCardless Payment Fetch Failed: {$errorMsg}");
        }

        $payments = $response->json('payments') ?? [];

        $syncedCount = 0;
        $skippedCount = 0;
        $runningBalance = $bankAccount->statement_balance;

        foreach ($payments as $payment) {
            $paymentId = $payment['id'] ?? null;
            if (! $paymentId) {
                continue;
            }

            // Check duplicate guard
            $exists = BankTransaction::where('club_id', $bankAccount->club_id)
                ->where('bank_account_id', $bankAccount->id)
                ->where('reference', $paymentId)
                ->exists();

            if ($exists) {
                $skippedCount++;

                continue;
            }

            // GoCardless amounts are in pence (e.g. 1500 = £15.00)
            $amountInPence = (int) ($payment['amount'] ?? 0);
            $amount = $amountInPence / 100.0;
            $status = $payment['status'] ?? 'confirmed';
            $description = $payment['description'] ?? "GoCardless Direct Debit ({$status})";
            $chargeDate = substr($payment['charge_date'] ?? ($payment['created_at'] ?? date('Y-m-d')), 0, 10);

            $runningBalance += $amount;

            BankTransaction::create([
                'club_id' => $bankAccount->club_id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $chargeDate,
                'description' => $description,
                'amount' => $amount,
                'reference' => $paymentId,
                'payee_payer' => 'GoCardless Customer',
                'status' => BankTransactionStatus::Unmatched->value,
                'balance_after' => $runningBalance,
            ]);

            $syncedCount++;
        }

        $bankAccount->update([
            'last_synced_at' => now(),
            'gocardless_connected_at' => $bankAccount->gocardless_connected_at ?? now(),
            'sync_status' => 'connected',
        ]);

        return [
            'success' => true,
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'last_synced_at' => now()->toIso8601String(),
        ];
    }
}
