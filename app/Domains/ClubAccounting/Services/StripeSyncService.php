<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use Exception;
use Illuminate\Support\Facades\Http;

class StripeSyncService
{
    /**
     * Test API connection to Stripe using Secret Key.
     */
    public function testConnection(string $secretKey): array
    {
        try {
            $response = Http::withToken(trim($secretKey))
                ->get('https://api.stripe.com/v1/balance');

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();

                return ['success' => false, 'message' => "Stripe Error: {$error}"];
            }

            return ['success' => true, 'message' => 'Successfully connected to Stripe API!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync settled Stripe transactions for a BankAccount.
     */
    public function syncTransactions(BankAccount $bankAccount, ?string $startDate = null, ?string $endDate = null): array
    {
        if (! $bankAccount->stripe_secret_key) {
            throw new Exception('Stripe Secret Key is not configured for this account.');
        }

        $secretKey = trim($bankAccount->stripe_secret_key);

        $params = [
            'limit' => 100,
        ];

        if ($startDate) {
            $params['created[gte]'] = strtotime($startDate);
        } elseif ($bankAccount->last_synced_at) {
            $params['created[gte]'] = strtotime('-1 day', $bankAccount->last_synced_at->timestamp);
        } else {
            $params['created[gte]'] = strtotime('-30 days');
        }

        if ($endDate) {
            $params['created[lte]'] = strtotime($endDate.' 23:59:59');
        }

        $response = Http::withToken($secretKey)
            ->get('https://api.stripe.com/v1/balance_transactions', $params);

        if ($response->failed()) {
            $bankAccount->update(['sync_status' => 'error']);
            $errorMsg = $response->json('error.message') ?? $response->body();
            throw new Exception("Stripe Transaction Fetch Failed: {$errorMsg}");
        }

        $data = $response->json();
        $transactions = $data['data'] ?? [];

        $syncedCount = 0;
        $skippedCount = 0;
        $runningBalance = $bankAccount->statement_balance;

        foreach ($transactions as $tx) {
            $txId = $tx['id'] ?? null;
            if (! $txId) {
                continue;
            }

            // Check duplicate guard
            $exists = BankTransaction::where('club_id', $bankAccount->club_id)
                ->where('bank_account_id', $bankAccount->id)
                ->where('reference', $txId)
                ->exists();

            if ($exists) {
                $skippedCount++;

                continue;
            }

            // Amounts in Stripe are in cents/pence (e.g. 1000 = £10.00)
            $gross = ((float) ($tx['amount'] ?? 0)) / 100;
            $fee = ((float) ($tx['fee'] ?? 0)) / 100;
            $net = ((float) ($tx['net'] ?? 0)) / 100;
            $type = $tx['type'] ?? 'charge';
            $description = $tx['description'] ?? ('Stripe '.ucfirst(str_replace('_', ' ', $type)));
            $date = date('Y-m-d', $tx['created'] ?? time());

            $runningBalance += $net;

            BankTransaction::create([
                'club_id' => $bankAccount->club_id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $date,
                'description' => $description,
                'amount' => $net,
                'reference' => $txId,
                'payee_payer' => 'Stripe Customer',
                'status' => BankTransactionStatus::Unmatched->value,
                'balance_after' => $runningBalance,
            ]);

            $syncedCount++;
        }

        $bankAccount->update([
            'last_synced_at' => now(),
            'stripe_connected_at' => $bankAccount->stripe_connected_at ?? now(),
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
