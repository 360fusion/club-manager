<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Services\GoCardlessSyncService;
use App\Domains\ClubAccounting\Services\PayPalSyncService;
use App\Domains\ClubAccounting\Services\StripeSyncService;
use App\Domains\ClubAccounting\Services\SumUpSyncService;
use App\Http\Requests\AccountingGateway\ConnectGoCardlessRequest;
use App\Http\Requests\AccountingGateway\ConnectPayPalRequest;
use App\Http\Requests\AccountingGateway\ConnectStripeRequest;
use App\Http\Requests\AccountingGateway\ConnectSumUpRequest;
use App\Http\Requests\AccountingGateway\StoreBankAccountRequest;
use App\Http\Requests\AccountingGateway\SyncGoCardlessTransactionsRequest;
use App\Http\Requests\AccountingGateway\SyncPayPalTransactionsRequest;
use App\Http\Requests\AccountingGateway\SyncStripeTransactionsRequest;
use App\Http\Requests\AccountingGateway\SyncSumUpTransactionsRequest;
use App\Models\Accounting\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bank accounts and the payment providers (PayPal, Stripe, SumUp, GoCardless) a club connects to bring in transactions. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingGatewayController extends Controller
{
    public function storeBankAccount(StoreBankAccountRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "{$validated['bank_name']} — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => $validated['bank_name'],
            'account_name' => $validated['account_name'],
            'account_type' => $validated['account_type'],
            'account_number' => $validated['account_number'] ?? null,
            'sort_code' => $validated['sort_code'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Bank account '{$validated['bank_name']} — {$validated['account_name']}' created and linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function toggleBankAccount(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $acc = BankAccount::where('club_id', $club->id)->findOrFail($id);
        $acc->update(['is_active' => ! $acc->is_active]);

        return redirect()->back()->with('success', 'Bank account status updated.');
    }

    public function connectPayPal(ConnectPayPalRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $syncService = new PayPalSyncService;
        $testResult = $syncService->testConnection(
            $validated['paypal_client_id'],
            $validated['paypal_client_secret'],
            $validated['paypal_environment']
        );

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "PayPal Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "PayPal — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'PayPal',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'paypal_client_id' => $validated['paypal_client_id'],
            'paypal_client_secret' => $validated['paypal_client_secret'],
            'paypal_environment' => $validated['paypal_environment'],
            'paypal_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "PayPal Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function testPayPalConnection(Request $request, string $clubSlug, int $id)
    {
        $club = $this->findClub($clubSlug);
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        if (! $bankAccount->paypal_client_id || ! $bankAccount->paypal_client_secret) {
            return response()->json(['success' => false, 'message' => 'PayPal credentials missing.'], 422);
        }

        $syncService = new PayPalSyncService;
        $res = $syncService->testConnection(
            $bankAccount->paypal_client_id,
            $bankAccount->paypal_client_secret,
            $bankAccount->paypal_environment ?? 'live'
        );

        return response()->json($res);
    }

    public function syncPayPalTransactions(SyncPayPalTransactionsRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validated();

        try {
            $syncService = new PayPalSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "PayPal API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "PayPal Sync Error: {$e->getMessage()}");
        }
    }

    public function connectStripe(ConnectStripeRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $syncService = new StripeSyncService;
        $testResult = $syncService->testConnection($validated['stripe_secret_key']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "Stripe Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "Stripe — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'Stripe',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'stripe_secret_key' => $validated['stripe_secret_key'],
            'stripe_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Stripe Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncStripeTransactions(SyncStripeTransactionsRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validated();

        try {
            $syncService = new StripeSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "Stripe API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Stripe Sync Error: {$e->getMessage()}");
        }
    }

    public function connectSumUp(ConnectSumUpRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $syncService = new SumUpSyncService;
        $testResult = $syncService->testConnection($validated['sumup_api_key']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "SumUp Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "SumUp — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'SumUp',
            'account_name' => $validated['account_name'],
            'account_type' => 'merchant',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'sumup_api_key' => $validated['sumup_api_key'],
            'sumup_merchant_code' => $testResult['merchant_code'] ?? null,
            'sumup_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "SumUp Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncSumUpTransactions(SyncSumUpTransactionsRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validated();

        try {
            $syncService = new SumUpSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "SumUp API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "SumUp Sync Error: {$e->getMessage()}");
        }
    }

    public function connectGoCardless(ConnectGoCardlessRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $syncService = new GoCardlessSyncService;
        $testResult = $syncService->testConnection($validated['gocardless_access_token'], $validated['gocardless_environment']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "GoCardless Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "GoCardless — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'GoCardless',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'gocardless_access_token' => $validated['gocardless_access_token'],
            'gocardless_environment' => $validated['gocardless_environment'],
            'gocardless_webhook_secret' => $validated['gocardless_webhook_secret'] ?? null,
            'gocardless_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "GoCardless Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncGoCardlessTransactions(SyncGoCardlessTransactionsRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validated();

        try {
            $syncService = new GoCardlessSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "GoCardless API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "GoCardless Sync Error: {$e->getMessage()}");
        }
    }
}
