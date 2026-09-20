<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalSyncService
{
    /**
     * Get base API URL based on environment.
     */
    protected function getBaseUrl(string $environment): string
    {
        return $environment === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Authenticate with PayPal OAuth 2.0 API.
     */
    public function authenticate(string $clientId, string $clientSecret, string $environment = 'live'): array
    {
        $baseUrl = $this->getBaseUrl($environment);

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            $error = $response->json('error_description') ?? $response->body();
            throw new Exception("PayPal Authentication Failed: {$error}");
        }

        return $response->json(); // ['access_token' => '...', 'expires_in' => 32400]
    }

    /**
     * Test connection to PayPal API.
     */
    public function testConnection(string $clientId, string $clientSecret, string $environment = 'live'): array
    {
        try {
            $authData = $this->authenticate($clientId, $clientSecret, $environment);
            
            return [
                'success' => true,
                'message' => 'Successfully connected to PayPal API (' . strtoupper($environment) . ')',
                'expires_in' => $authData['expires_in'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync settled PayPal transactions for a BankAccount within optional date ranges.
     */
    public function syncTransactions(BankAccount $bankAccount, ?string $startDate = null, ?string $endDate = null): array
    {
        if (! $bankAccount->paypal_client_id || ! $bankAccount->paypal_client_secret) {
            throw new Exception("PayPal API credentials are not configured for this account.");
        }

        $environment = $bankAccount->paypal_environment ?? 'live';
        $auth = $this->authenticate($bankAccount->paypal_client_id, $bankAccount->paypal_client_secret, $environment);
        $accessToken = $auth['access_token'];

        $baseUrl = $this->getBaseUrl($environment);

        if ($startDate) {
            $start = date('Y-m-d\TH:i:s\Z', strtotime($startDate));
        } elseif ($bankAccount->last_synced_at) {
            // Subtract 1 day for safety margin
            $start = date('Y-m-d\TH:i:s\Z', strtotime('-1 day', $bankAccount->last_synced_at->timestamp));
        } else {
            $start = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        }

        $end = $endDate ? date('Y-m-d\TH:i:s\Z', strtotime($endDate . ' 23:59:59')) : date('Y-m-d\TH:i:s\Z');

        $response = Http::withToken($accessToken)
            ->get("{$baseUrl}/v1/reporting/transactions", [
                'start_date' => $start,
                'end_date' => $end,
                'fields' => 'all',
                'page_size' => 100,
            ]);

        if ($response->failed()) {
            $bankAccount->update(['sync_status' => 'error']);
            $errorMsg = $response->json('message') ?? $response->body();
            throw new Exception("PayPal Transaction Fetch Failed: {$errorMsg}");
        }

        $data = $response->json();
        $txDetails = $data['transaction_details'] ?? [];

        $syncedCount = 0;
        $skippedCount = 0;
        $runningBalance = $bankAccount->statement_balance;

        foreach ($txDetails as $tx) {
            $info = $tx['transaction_info'] ?? [];
            $payer = $tx['payer_info'] ?? [];

            $txId = $info['transaction_id'] ?? null;
            if (! $txId) continue;

            // Check if already synced (Duplicate Guard)
            $exists = BankTransaction::where('club_id', $bankAccount->club_id)
                ->where('bank_account_id', $bankAccount->id)
                ->where('reference', $txId)
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            $gross = (float)($info['transaction_amount']['value'] ?? 0);
            $fee = (float)($info['fee_amount']['value'] ?? 0);
            $net = $gross - abs($fee);

            $payerName = $payer['payer_name']['alternate_full_name'] ?? $payer['email_address'] ?? 'PayPal Customer';
            $date = date('Y-m-d', strtotime($info['transaction_initiation_date'] ?? now()));

            $runningBalance += $net;

            BankTransaction::create([
                'club_id' => $bankAccount->club_id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $date,
                'description' => "PayPal Payment from {$payerName}",
                'amount' => $net,
                'reference' => $txId,
                'payee_payer' => $payerName,
                'status' => BankTransactionStatus::Unmatched->value,
                'balance_after' => $runningBalance,
            ]);

            $syncedCount++;
        }

        $bankAccount->update([
            'last_synced_at' => now(),
            'paypal_connected_at' => $bankAccount->paypal_connected_at ?? now(),
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
