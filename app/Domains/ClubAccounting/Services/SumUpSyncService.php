<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use Exception;
use Illuminate\Support\Facades\Http;

class SumUpSyncService
{
    /**
     * Test connection to SumUp API.
     */
    public function testConnection(string $apiKey): array
    {
        try {
            $response = Http::withToken(trim($apiKey))
                ->get('https://api.sumup.com/v0.1/me');

            if ($response->failed()) {
                $error = $response->json('message') ?? $response->body();
                return ['success' => false, 'message' => "SumUp Connection Failed: {$error}"];
            }

            $profile = $response->json();
            $merchantCode = $profile['merchant_profile']['merchant_code'] ?? null;

            return [
                'success' => true,
                'message' => 'Successfully connected to SumUp API!',
                'merchant_code' => $merchantCode,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync settled SumUp transactions for a BankAccount.
     */
    public function syncTransactions(BankAccount $bankAccount, ?string $startDate = null, ?string $endDate = null): array
    {
        if (! $bankAccount->sumup_api_key) {
            throw new Exception("SumUp API Key is not configured for this account.");
        }

        $apiKey = trim($bankAccount->sumup_api_key);

        $start = $startDate ? date('Y-m-d', strtotime($startDate)) : date('Y-m-d', strtotime('-30 days'));
        $end = $endDate ? date('Y-m-d', strtotime($endDate)) : date('Y-m-d');

        $response = Http::withToken($apiKey)
            ->get('https://api.sumup.com/v0.1/me/financials/transactions', [
                'start_date' => $start,
                'end_date' => $end,
                'limit' => 100,
            ]);

        if ($response->failed()) {
            $bankAccount->update(['sync_status' => 'error']);
            $errorMsg = $response->json('message') ?? $response->body();
            throw new Exception("SumUp Transaction Fetch Failed: {$errorMsg}");
        }

        $data = $response->json();
        $transactions = $data['items'] ?? $data['financial_transactions'] ?? (is_array($data) ? $data : []);

        $syncedCount = 0;
        $skippedCount = 0;
        $runningBalance = $bankAccount->statement_balance;

        foreach ($transactions as $tx) {
            $txId = $tx['transaction_code'] ?? $tx['id'] ?? null;
            if (! $txId) continue;

            // Check duplicate guard
            $exists = BankTransaction::where('club_id', $bankAccount->club_id)
                ->where('bank_account_id', $bankAccount->id)
                ->where('reference', $txId)
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            $gross = (float)($tx['amount'] ?? 0);
            $fee = (float)($tx['fee_amount'] ?? 0);
            $net = $gross - abs($fee);
            $date = date('Y-m-d', strtotime($tx['timestamp'] ?? $tx['date'] ?? now()));
            $desc = "SumUp Card Reader Payment — " . ($tx['transaction_code'] ?? 'POS');

            $runningBalance += $net;

            BankTransaction::create([
                'club_id' => $bankAccount->club_id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $date,
                'description' => $desc,
                'amount' => $net,
                'reference' => $txId,
                'payee_payer' => 'SumUp Customer',
                'status' => BankTransactionStatus::Unmatched->value,
                'balance_after' => $runningBalance,
            ]);

            $syncedCount++;
        }

        $bankAccount->update([
            'last_synced_at' => now(),
            'sumup_connected_at' => $bankAccount->sumup_connected_at ?? now(),
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
