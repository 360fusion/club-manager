<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankMatchRule;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Http\Requests\AccountingAdmin\IgnoreBankTransactionRequest;
use App\Http\Requests\AccountingAdmin\ImportBankStatementRequest;
use App\Http\Requests\AccountingAdmin\ReconcileBankTransactionRequest;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bank statement import and reconciliation actions. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingBankController extends Controller
{
    public function reconcileBankTransaction(ReconcileBankTransactionRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $tx = BankTransaction::where('club_id', $club->id)
            ->findOrFail($validated['transaction_id']);

        $matcher = app(BankReconciliationMatcherService::class);
        $matcher->reconcileTransaction($tx, $validated['match_type'], $validated['target_id'], [
            'nominal_code' => $validated['nominal_code'] ?? 'GENERAL',
        ]);

        return redirect()->back()->with('success', 'Transaction successfully reconciled!');
    }

    public function forgetBankMatchRule(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        BankMatchRule::where('club_id', $club->id)->where('id', $id)->firstOrFail()->delete();

        return redirect()->back()->with('success', 'Forgotten — that payee will be matched from scratch next time.');
    }

    public function ignoreBankTransaction(IgnoreBankTransactionRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $validated = $request->validated();

        $tx = BankTransaction::where('club_id', $club->id)
            ->findOrFail($validated['transaction_id']);

        $matcher = app(BankReconciliationMatcherService::class);
        $matcher->ignoreTransaction($tx);

        return redirect()->back()->with('success', 'Transaction line ignored.');
    }

    public function deleteBankStatementLines(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $ids = (array) $request->input('transaction_ids', []);

        if (! empty($ids)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $ids)
                ->update(['status' => BankTransactionStatus::Ignored->value]);
        }

        return redirect()->back()->with('success', count($ids).' statement line(s) deleted.');
    }

    public function restoreBankStatementLines(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $ids = (array) $request->input('transaction_ids', []);

        if (! empty($ids)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $ids)
                ->update(['status' => BankTransactionStatus::Unmatched->value]);
        }

        return redirect()->back()->with('success', count($ids).' statement line(s) restored.');
    }

    public function removeAndRedoAccountTransactions(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $rawIds = (array) $request->input('transaction_ids', []);

        $txIds = [];
        foreach ($rawIds as $id) {
            if (is_numeric($id)) {
                $txIds[] = (int) $id;
            } elseif (is_string($id) && str_starts_with($id, 'tx_')) {
                $txIds[] = (int) str_replace('tx_', '', $id);
            }
        }

        if (! empty($txIds)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $txIds)
                ->update(['status' => BankTransactionStatus::Unmatched->value]);
        }

        return redirect()->back()->with('success', count($txIds).' transaction(s) un-reconciled and returned to the Reconcile queue.');
    }

    public function importBankStatement(ImportBankStatementRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $parser = app(BankStatementParserService::class);
        $file = $request->file('statement_file');

        $bundle = $parser->parseFile($file->getRealPath(), $file->getClientOriginalName(), $club->id);
        $parser->importParsedBundle($club, $bundle);

        return redirect()->back()->with('success', "Imported {$bundle['new_lines_count']} new statement lines successfully!");
    }
}
