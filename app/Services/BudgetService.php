<?php

namespace App\Services;

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Club;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Set/replace a club's budget lines for a financial year in one go.
     *
     * @param  array<int, array{account_id: int, budgeted_amount: float, notes?: ?string}>  $lines
     */
    public function setBudgetLines(Club $club, int $year, array $lines): void
    {
        DB::transaction(function () use ($club, $year, $lines) {
            foreach ($lines as $line) {
                Budget::updateOrCreate(
                    ['club_id' => $club->id, 'financial_year' => $year, 'account_id' => $line['account_id']],
                    ['budgeted_amount' => $line['budgeted_amount'], 'notes' => $line['notes'] ?? null]
                );
            }
        });
    }

    /**
     * Budget vs actual for every revenue/expense account for a calendar year, with
     * variance and variance %. Accounts with neither a budget nor any activity that
     * year are left out.
     *
     * @return array{financial_year: int, rows: array, total_budgeted: float, total_actual: float}
     */
    public function getBudgetVsActual(Club $club, int $year): array
    {
        $accounts = Account::where('club_id', $club->id)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $budgets = Budget::where('club_id', $club->id)->where('financial_year', $year)->get()->keyBy('account_id');

        $rows = [];
        $totalBudgeted = 0.0;
        $totalActual = 0.0;

        foreach ($accounts as $account) {
            $budgeted = (float) ($budgets[$account->id]->budgeted_amount ?? 0);
            $actual = $this->actualForAccountAndYear($account, $year);

            if ($budgeted == 0.0 && $actual == 0.0) {
                continue;
            }

            $variance = round($actual - $budgeted, 2);
            $variancePct = $budgeted != 0.0 ? round(($variance / abs($budgeted)) * 100, 1) : null;

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'budgeted' => round($budgeted, 2),
                'actual' => round($actual, 2),
                'variance' => $variance,
                'variance_pct' => $variancePct,
            ];

            $totalBudgeted += $budgeted;
            $totalActual += $actual;
        }

        return [
            'financial_year' => $year,
            'rows' => $rows,
            'total_budgeted' => round($totalBudgeted, 2),
            'total_actual' => round($totalActual, 2),
        ];
    }

    private function actualForAccountAndYear(Account $account, int $year): float
    {
        $debits = (float) DB::table('accounting_journal_items')
            ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_items.journal_entry_id')
            ->where('accounting_journal_entries.club_id', $account->club_id)
            ->where('accounting_journal_items.account_id', $account->id)
            ->whereYear('accounting_journal_entries.entry_date', $year)
            ->where('accounting_journal_entries.status', '!=', 'void')
            ->sum('accounting_journal_items.debit');

        $credits = (float) DB::table('accounting_journal_items')
            ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_items.journal_entry_id')
            ->where('accounting_journal_entries.club_id', $account->club_id)
            ->where('accounting_journal_items.account_id', $account->id)
            ->whereYear('accounting_journal_entries.entry_date', $year)
            ->where('accounting_journal_entries.status', '!=', 'void')
            ->sum('accounting_journal_items.credit');

        return $account->type === 'expense' ? $debits - $credits : $credits - $debits;
    }
}
