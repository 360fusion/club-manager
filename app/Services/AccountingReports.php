<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\Member;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\MeetingFinancialReturn;
use App\Models\Club;
use App\Models\Invoice;
use App\Support\Csv;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Financial summaries, the standard reports, the VAT return and the comparative income and expenditure figures, as data, CSV or the data a PDF view needs. Split out of AccountingService.
 */
class AccountingReports
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Get Overall Financial Summary KPIs
     */
    public function getFinancialSummary(Club $club): array
    {
        $this->accounting->seedDefaultAccounts($club);

        $accounts = Account::where('club_id', $club->id)->get();
        Account::loadTotals($accounts);

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($accounts as $acc) {
            $bal = $acc->balance;
            switch ($acc->type) {
                case 'asset':
                    $totalAssets += $bal;
                    break;
                case 'liability':
                    $totalLiabilities += $bal;
                    break;
                case 'equity':
                    $totalEquity += $bal;
                    break;
                case 'revenue':
                    $totalRevenue += $bal;
                    break;
                case 'expense':
                    $totalExpenses += $bal;
                    break;
            }
        }

        $netIncome = $totalRevenue - $totalExpenses;

        $unpaidBillsTotal = (float) Bill::where('club_id', $club->id)->where('status', DocumentStatus::Unpaid->value)->sum('amount');
        $unpaidInvoicesTotal = (float) Invoice::where('club_id', $club->id)->where('status', DocumentStatus::Unpaid->value)->sum('amount');

        return [
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($netIncome, 2),
            'unpaid_bills_total' => round($unpaidBillsTotal, 2),
            'unpaid_invoices_total' => round($unpaidInvoicesTotal, 2),
        ];
    }

    /**
     * Get detailed financial report breakdown for the 7 standard ERP reports
     */
    public function getReportsData(Club $club): array
    {
        $this->accounting->seedDefaultAccounts($club);
        $summary = $this->getFinancialSummary($club);
        $accounts = Account::where('club_id', $club->id)->orderBy('code')->get();
        Account::loadTotals($accounts);

        // 1. Account Summary
        $accountSummary = $accounts->map(function ($acc) {
            ['debit' => $debits, 'credit' => $credits] = $acc->totals();

            return [
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'total_debit' => round($debits, 2),
                'total_credit' => round($credits, 2),
                'net_balance' => round($acc->balance, 2),
            ];
        });

        // 2. Aged Payables Summary — includes bills pending a second sign-off (not yet
        // on the ledger) alongside unpaid ones, since both are genuinely owed; the
        // 'pending_approval' status on each item lets the UI label them distinctly.
        $bills = Bill::where('club_id', $club->id)->whereIn('status', [DocumentStatus::Unpaid->value, DocumentStatus::PendingApproval->value])->get();
        $agedPayables = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
            'total' => 0.0,
            'items' => [],
        ];

        foreach ($bills as $b) {
            $daysOverdue = max(0, (int) now()->diffInDays($b->due_date, false) * -1);
            $amt = (float) $b->amount;
            $agedPayables['total'] += $amt;

            if ($daysOverdue === 0) {
                $agedPayables['current'] += $amt;
                $bucket = 'Current';
            } elseif ($daysOverdue <= 30) {
                $agedPayables['1_30'] += $amt;
                $bucket = '1 - 30 Days';
            } elseif ($daysOverdue <= 60) {
                $agedPayables['31_60'] += $amt;
                $bucket = '31 - 60 Days';
            } elseif ($daysOverdue <= 90) {
                $agedPayables['61_90'] += $amt;
                $bucket = '61 - 90 Days';
            } else {
                $agedPayables['90_plus'] += $amt;
                $bucket = '90+ Days';
            }

            $agedPayables['items'][] = [
                'bill_number' => $b->bill_number,
                'vendor_name' => $b->vendor_name,
                'due_date' => $b->due_date->format('d M Y'),
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'amount' => $amt,
                'status' => $b->status,
            ];
        }

        // 3. Aged Receivables Summary — see the Payables comment above; same reasoning.
        $invoices = Invoice::where('club_id', $club->id)->whereIn('status', [DocumentStatus::Unpaid->value, DocumentStatus::PendingApproval->value])->with('user')->get();
        $agedReceivables = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
            'total' => 0.0,
            'items' => [],
        ];

        foreach ($invoices as $inv) {
            $daysOverdue = max(0, (int) now()->diffInDays($inv->created_at->addDays(30), false) * -1);
            $amt = (float) $inv->amount;
            $agedReceivables['total'] += $amt;

            if ($daysOverdue === 0) {
                $agedReceivables['current'] += $amt;
                $bucket = 'Current';
            } elseif ($daysOverdue <= 30) {
                $agedReceivables['1_30'] += $amt;
                $bucket = '1 - 30 Days';
            } elseif ($daysOverdue <= 60) {
                $agedReceivables['31_60'] += $amt;
                $bucket = '31 - 60 Days';
            } elseif ($daysOverdue <= 90) {
                $agedReceivables['61_90'] += $amt;
                $bucket = '61 - 90 Days';
            } else {
                $agedReceivables['90_plus'] += $amt;
                $bucket = '90+ Days';
            }

            $agedReceivables['items'][] = [
                'invoice_number' => $inv->invoice_number,
                'recipient_name' => $inv->user ? $inv->user->name : 'Member',
                'created_at' => $inv->created_at->format('d M Y'),
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'amount' => $amt,
                'status' => $inv->status,
            ];
        }

        // 4. Balance Sheet Summary
        $balanceSheet = [
            'assets' => $accounts->where('type', 'asset')->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'liabilities' => $accounts->where('type', 'liability')->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'equity' => $accounts->where('type', 'equity')->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'total_assets' => $summary['total_assets'],
            'total_liabilities' => $summary['total_liabilities'],
            'total_equity' => $summary['total_equity'],
        ];

        // 5. Cash Summary
        $cashAccounts = $accounts->whereIn('code', ['1000', '1100']);
        $cashSummary = [
            'total_cash_on_hand' => round($cashAccounts->sum('balance'), 2),
            'accounts' => $cashAccounts->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
        ];

        // 6. Executive Summary
        $grossMarginPct = $summary['total_revenue'] > 0 ? round((($summary['total_revenue'] - $summary['total_expenses']) / $summary['total_revenue']) * 100, 1) : 0;
        $operatingRatio = $summary['total_revenue'] > 0 ? round(($summary['total_expenses'] / $summary['total_revenue']) * 100, 1) : 0;
        $execSummary = [
            'net_profit_margin_pct' => $grossMarginPct,
            'operating_expense_ratio_pct' => $operatingRatio,
            'total_cash_reserves' => $cashSummary['total_cash_on_hand'],
            'outstanding_ar' => $agedReceivables['total'],
            'outstanding_ap' => $agedPayables['total'],
        ];

        // 7. Profit and Loss
        $profitAndLoss = [
            'revenues' => $accounts->where('type', 'revenue')->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'amount' => round($a->balance, 2)]),
            'expenses' => $accounts->where('type', 'expense')->values()->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'amount' => round($a->balance, 2)]),
            'total_revenue' => $summary['total_revenue'],
            'total_expenses' => $summary['total_expenses'],
            'net_income' => $summary['net_income'],
        ];

        // 8. Comparative Annual Income & Expenditure Statement
        $comparativeStatement = $this->getComparativeIncomeExpenditureData($club);

        // 9. VAT Return (only meaningful once the club has VAT enabled; empty otherwise)
        $vatReturn = $this->getVatReturnData($club);

        return [
            'account_summary' => $accountSummary,
            'aged_payables' => $agedPayables,
            'aged_receivables' => $agedReceivables,
            'balance_sheet' => $balanceSheet,
            'cash_summary' => $cashSummary,
            'executive_summary' => $execSummary,
            'profit_and_loss' => $profitAndLoss,
            'comparative_income_expenditure' => $comparativeStatement,
            'vat_return' => $vatReturn,
            'provincial_due' => $this->getProvincialDueWorksheet($club),
        ];
    }

    /**
     * What the lodge owes its Province: per-capita dues plus any Festival contribution,
     * each rate × active members. A rate the Province has not set stays null rather than
     * defaulting to zero, so the lodge is told to ask rather than led to think nothing is owed.
     * Member counts are always "current": members carry no leave date, so a true
     * as-of-date snapshot is not possible.
     *
     * @return array{province_name: ?string, has_province: bool, member_count: int, per_capita_rate: ?float, per_capita_amount: ?float, festival_rate: ?float, festival_amount: ?float, total_due: ?float, counted_on: string}
     */
    public function getProvincialDueWorksheet(Club $club): array
    {
        $province = $club->province;
        $count = Member::where('club_id', $club->id)->active()->count();

        $perCapitaRate = $province?->per_capita_rate !== null ? (float) $province->per_capita_rate : null;
        $festivalRate = $province?->festival_contribution_rate !== null ? (float) $province->festival_contribution_rate : null;

        $perCapitaAmount = $perCapitaRate !== null ? round($perCapitaRate * $count, 2) : null;
        $festivalAmount = $festivalRate !== null ? round($festivalRate * $count, 2) : null;

        return [
            'province_name' => $province?->name,
            'has_province' => $province !== null,
            'member_count' => $count,
            'per_capita_rate' => $perCapitaRate,
            'per_capita_amount' => $perCapitaAmount,
            'festival_rate' => $festivalRate,
            'festival_amount' => $festivalAmount,
            'total_due' => $perCapitaAmount === null && $festivalAmount === null ? null : round(($perCapitaAmount ?? 0) + ($festivalAmount ?? 0), 2),
            'counted_on' => now()->format('d M Y'),
        ];
    }

    /**
     * A simplified UK VAT-return-style summary for one quarter: output VAT collected
     * on member invoices minus input VAT reclaimed on vendor bills = net VAT due to
     * HMRC. This is calculated and exportable, not submitted — filing via HMRC's
     * Making Tax Digital service is a separate, later step for the treasurer.
     *
     * @return array{enabled: bool, quarter_label: string, quarter_start: string, quarter_end: string, output_vat: float, input_vat: float, net_vat_due: float, net_sales: float, net_purchases: float, rows: array}
     */
    public function getVatReturnData(Club $club, ?string $quarterStartDate = null): array
    {
        $quarterStart = $quarterStartDate ? Carbon::parse($quarterStartDate)->startOfDay() : now()->firstOfQuarter();
        $quarterEnd = $quarterStart->copy()->endOfQuarter();

        if (! $club->vatIsEnabled()) {
            return [
                'enabled' => false,
                'quarter_label' => $quarterStart->format('Y').' Q'.$quarterStart->quarter,
                'quarter_start' => $quarterStart->format('Y-m-d'),
                'quarter_end' => $quarterEnd->format('Y-m-d'),
                'output_vat' => 0.0,
                'input_vat' => 0.0,
                'net_vat_due' => 0.0,
                'net_sales' => 0.0,
                'net_purchases' => 0.0,
                'rows' => [],
            ];
        }

        $invoices = Invoice::where('club_id', $club->id)
            ->whereNotNull('vat_amount')
            ->whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->get();

        $bills = Bill::where('club_id', $club->id)
            ->whereNotNull('vat_amount')
            ->whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->get();

        $outputVat = round((float) $invoices->sum('vat_amount'), 2);
        $inputVat = round((float) $bills->sum('vat_amount'), 2);
        $netSales = round((float) $invoices->sum('net_amount'), 2);
        $netPurchases = round((float) $bills->sum('net_amount'), 2);

        $rows = $invoices->map(fn ($inv) => [
            'type' => 'sale',
            'reference' => $inv->invoice_number,
            'description' => $inv->title,
            'date' => $inv->created_at->format('Y-m-d'),
            'net_amount' => (float) $inv->net_amount,
            'vat_amount' => (float) $inv->vat_amount,
            'gross_amount' => (float) $inv->amount,
        ])->concat($bills->map(fn ($bill) => [
            'type' => 'purchase',
            'reference' => $bill->bill_number,
            'description' => $bill->vendor_name,
            'date' => $bill->created_at->format('Y-m-d'),
            'net_amount' => (float) $bill->net_amount,
            'vat_amount' => (float) $bill->vat_amount,
            'gross_amount' => (float) $bill->amount,
        ]))->sortBy('date')->values();

        return [
            'enabled' => true,
            'quarter_label' => $quarterStart->format('Y').' Q'.$quarterStart->quarter,
            'quarter_start' => $quarterStart->format('Y-m-d'),
            'quarter_end' => $quarterEnd->format('Y-m-d'),
            'output_vat' => $outputVat,
            'input_vat' => $inputVat,
            'net_vat_due' => round($outputVat - $inputVat, 2),
            'net_sales' => $netSales,
            'net_purchases' => $netPurchases,
            'rows' => $rows,
        ];
    }

    /**
     * CSV export of one quarter's VAT return detail (see getVatReturnData()), for a
     * treasurer to file manually via HMRC's portal or MTD bridging software.
     */
    public function getVatReturnCsv(Club $club, ?string $quarterStartDate = null): string
    {
        $data = $this->getVatReturnData($club, $quarterStartDate);

        $csv = Csv::line(['VAT Return', $club->name, $data['quarter_label'], $data['quarter_start'].' to '.$data['quarter_end']]);
        $csv .= Csv::line([]);
        $csv .= Csv::line(['Date', 'Type', 'Reference', 'Description', 'Net Amount', 'VAT Amount', 'Gross Amount']);

        foreach ($data['rows'] as $row) {
            $csv .= Csv::line([
                $row['date'],
                $row['type'] === 'sale' ? 'Sale (Output VAT)' : 'Purchase (Input VAT)',
                $row['reference'],
                $row['description'],
                number_format($row['net_amount'], 2, '.', ''),
                number_format($row['vat_amount'], 2, '.', ''),
                number_format($row['gross_amount'], 2, '.', ''),
            ]);
        }

        $csv .= Csv::line([]);
        $csv .= Csv::line(['Net Sales', number_format($data['net_sales'], 2, '.', '')]);
        $csv .= Csv::line(['Net Purchases', number_format($data['net_purchases'], 2, '.', '')]);
        $csv .= Csv::line(['Output VAT (on sales)', number_format($data['output_vat'], 2, '.', '')]);
        $csv .= Csv::line(['Input VAT (on purchases)', number_format($data['input_vat'], 2, '.', '')]);
        $csv .= Csv::line(['Net VAT Due to HMRC', number_format($data['net_vat_due'], 2, '.', '')]);

        return $csv;
    }

    /**
     * Build 4-Column Comparative Annual Income & Expenditure Statement from real ledger,
     * bill/invoice and meeting-return data for the current and prior calendar year. No
     * category ever falls back to a placeholder figure — a category with no activity
     * in a year is reported as 0.
     */
    public function getComparativeIncomeExpenditureData(Club $club): array
    {
        $currentYear = $club->financialYearFor(now());
        $priorYear = $currentYear - 1;

        $current = $this->comparativeIncomeExpenditureFiguresForYear($club, $currentYear);
        $prior = $this->comparativeIncomeExpenditureFiguresForYear($club, $priorYear);

        $rows = [];
        foreach ($current as $key => $currentFigures) {
            $rows[] = [
                'category' => $currentFigures['label'],
                'prior_income' => $prior[$key]['income'],
                'prior_expenditure' => $prior[$key]['expenditure'],
                'current_income' => $currentFigures['income'],
                'current_expenditure' => $currentFigures['expenditure'],
            ];
        }

        $priorTotalIncome = array_sum(array_column($rows, 'prior_income'));
        $priorTotalExp = array_sum(array_column($rows, 'prior_expenditure'));
        $priorBalanceCarriedForward = $priorTotalIncome - $priorTotalExp;

        $currentTotalIncome = array_sum(array_column($rows, 'current_income'));
        $currentTotalExp = array_sum(array_column($rows, 'current_expenditure'));
        $currentBalanceCarriedForward = $currentTotalIncome - $currentTotalExp;

        return [
            'prior_year_label' => (string) $priorYear,
            'current_year_label' => (string) $currentYear,
            'rows' => $rows,
            'prior_totals' => ['income' => round($priorTotalIncome, 2), 'expenditure' => round($priorTotalExp, 2)],
            'prior_balance_carried_forward' => round($priorBalanceCarriedForward, 2),
            'prior_reconciled' => round($priorTotalIncome, 2),

            'current_totals' => ['income' => round($currentTotalIncome, 2), 'expenditure' => round($currentTotalExp, 2)],
            'current_balance_carried_forward' => round($currentBalanceCarriedForward, 2),
            'current_reconciled' => round($currentTotalIncome, 2),
        ];
    }

    /**
     * Real per-category income/expenditure totals for one calendar year, used by
     * getComparativeIncomeExpenditureData(). Subs income/expenditure come from paid
     * invoices/bills settled that year; the charity categories come from finalised
     * (non-draft) meeting financial returns for that year.
     *
     * @return array<string, array{label: string, income: float, expenditure: float}>
     */
    private function comparativeIncomeExpenditureFiguresForYear(Club $club, int $year): array
    {
        $range = $club->financialYearRange($year);
        $days = array_map(fn ($day) => $day->toDateString(), $range);

        $subsIncome = (float) Invoice::where('club_id', $club->id)
            ->where('status', DocumentStatus::Paid->value)
            ->whereBetween('paid_at', $range)
            ->sum('amount');

        $subsExpenditure = (float) Bill::where('club_id', $club->id)
            ->where('status', DocumentStatus::Paid->value)
            ->whereBetween('paid_at', $range)
            ->sum('amount');

        $returns = MeetingFinancialReturn::where('club_id', $club->id)
            ->where('is_draft', false)
            ->whereBetween('return_date', $days)
            ->get();

        return [
            'subs' => [
                'label' => 'SUBS / LODGE FUNDS',
                'income' => round($subsIncome, 2),
                'expenditure' => round($subsExpenditure, 2),
            ],
            'almoner' => [
                'label' => 'ALMONER (ALMS COLLECTIONS)',
                'income' => round((float) $returns->sum('alms_amount'), 2),
                'expenditure' => 0.00,
            ],
            'raffle' => [
                'label' => 'RAFFLE (CHARITY CONTRIBUTIONS)',
                'income' => round((float) $returns->sum('raffle_amount'), 2),
                'expenditure' => 0.00,
            ],
            'donations' => [
                'label' => 'DONATIONS',
                'income' => round((float) $returns->sum('donations_amount'), 2),
                'expenditure' => 0.00,
            ],
            'bequest' => [
                'label' => 'BEQUEST',
                'income' => round((float) $returns->sum('bequest_amount'), 2),
                'expenditure' => 0.00,
            ],
        ];
    }

    /**
     * CSV export for any of the reports in getReportsData(), so a treasurer can file
     * or forward one without relying on the browser's print dialog.
     */
    public function getReportCsv(Club $club, string $reportKey, ?int $budgetYear = null): string
    {
        if ($reportKey === 'budget_vs_actual') {
            return $this->csvFromRows(
                ['Code', 'Name', 'Type', 'Budgeted', 'Actual', 'Variance'],
                collect(app(BudgetService::class)->getBudgetVsActual($club, $budgetYear ?? $club->financialYearFor(now()))['rows'])
                    ->map(fn ($r) => [$r['code'], $r['name'], $r['type'], $r['budgeted'], $r['actual'], $r['variance']])
            );
        }

        $reports = $this->getReportsData($club);
        $report = $reports[$reportKey] ?? null;

        if ($report === null) {
            throw new InvalidArgumentException("Unknown report: {$reportKey}");
        }

        return match ($reportKey) {
            'account_summary' => $this->csvFromRows(
                ['Code', 'Name', 'Type', 'Total Debit', 'Total Credit', 'Net Balance'],
                collect($report)->map(fn ($r) => [$r['code'], $r['name'], $r['type'], $r['total_debit'], $r['total_credit'], $r['net_balance']])
            ),
            'aged_payables', 'aged_receivables' => $this->csvFromRows(
                ['Reference', 'Name', 'Days Overdue', 'Bucket', 'Amount', 'Status'],
                collect($report['items'])->map(fn ($r) => [$r['bill_number'] ?? $r['invoice_number'] ?? '', $r['vendor_name'] ?? $r['recipient_name'] ?? '', $r['days_overdue'], $r['bucket'], $r['amount'], $r['status'] === DocumentStatus::PendingApproval->value ? 'Pending Approval' : 'Unpaid'])
            ),
            'balance_sheet' => $this->csvFromRows(
                ['Section', 'Code', 'Name', 'Balance'],
                collect($report['assets'])->map(fn ($r) => ['Asset', $r['code'], $r['name'], $r['balance']])
                    ->concat(collect($report['liabilities'])->map(fn ($r) => ['Liability', $r['code'], $r['name'], $r['balance']]))
                    ->concat(collect($report['equity'])->map(fn ($r) => ['Equity', $r['code'], $r['name'], $r['balance']]))
            ),
            'cash_summary' => $this->csvFromRows(
                ['Code', 'Name', 'Balance'],
                collect($report['accounts'])->map(fn ($r) => [$r['code'], $r['name'], $r['balance']])
            ),
            'executive_summary' => $this->csvFromRows(
                ['Metric', 'Value'],
                collect([
                    ['Net Profit Margin %', $report['net_profit_margin_pct']],
                    ['Operating Expense Ratio %', $report['operating_expense_ratio_pct']],
                    ['Total Cash Reserves', $report['total_cash_reserves']],
                    ['Outstanding Receivables', $report['outstanding_ar']],
                    ['Outstanding Payables', $report['outstanding_ap']],
                ])
            ),
            'profit_and_loss' => $this->csvFromRows(
                ['Section', 'Code', 'Name', 'Amount'],
                collect($report['revenues'])->map(fn ($r) => ['Revenue', $r['code'], $r['name'], $r['amount']])
                    ->concat(collect($report['expenses'])->map(fn ($r) => ['Expense', $r['code'], $r['name'], $r['amount']]))
            ),
            'provincial_due' => $this->csvFromRows(
                ['Item', 'Members', 'Rate', 'Amount'],
                collect([
                    ['Per-capita dues', $report['member_count'], $report['per_capita_rate'] ?? 'not set', $report['per_capita_amount'] ?? 'not set'],
                    ['Festival contribution', $report['member_count'], $report['festival_rate'] ?? 'not set', $report['festival_amount'] ?? 'not set'],
                    ['Total due', '', '', $report['total_due'] ?? 'not set'],
                ])
            ),
            'comparative_income_expenditure' => $this->csvFromRows(
                ['Category', "Prior Income ({$report['prior_year_label']})", "Prior Expenditure ({$report['prior_year_label']})", "Current Income ({$report['current_year_label']})", "Current Expenditure ({$report['current_year_label']})"],
                collect($report['rows'])->map(fn ($r) => [$r['category'], $r['prior_income'], $r['prior_expenditure'], $r['current_income'], $r['current_expenditure']])
            ),
            default => throw new InvalidArgumentException("No CSV export defined for report: {$reportKey}"),
        };
    }

    /**
     * The Blade view + data for a report's PDF, shared by exportReportPdf(). Covers the
     * 8 reports that only had CSV export before (Comparative I&E and the VAT Return
     * already have their own bespoke PDF templates).
     *
     * @return array{0: string, 1: array}
     */
    public function getReportPdfView(Club $club, string $reportKey, ?int $budgetYear = null): array
    {
        $titles = [
            'account_summary' => 'Account Summary',
            'aged_payables' => 'Aged Payables',
            'aged_receivables' => 'Aged Receivables',
            'balance_sheet' => 'Balance Sheet',
            'cash_summary' => 'Cash Summary',
            'executive_summary' => 'Executive Summary',
            'profit_and_loss' => 'Profit & Loss',
            'budget_vs_actual' => 'Budget vs Actual',
            'provincial_due' => 'Due to Province',
        ];

        if (! isset($titles[$reportKey])) {
            throw new InvalidArgumentException("No PDF export defined for report: {$reportKey}");
        }

        $report = $reportKey === 'budget_vs_actual'
            ? app(BudgetService::class)->getBudgetVsActual($club, $budgetYear ?? $club->financialYearFor(now()))
            : $this->getReportsData($club)[$reportKey];

        return ['pdf.accounting.report', [
            'reportKey' => $reportKey,
            'reportTitle' => $titles[$reportKey],
            'report' => $report,
            'club' => $club,
            'cs' => $club->currencySymbol(),
        ]];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array-key, array<int, mixed>>  $rows
     */
    private function csvFromRows(array $headers, $rows): string
    {
        $csv = Csv::line($headers);
        foreach ($rows as $row) {
            $csv .= Csv::line($row);
        }

        return $csv;
    }
}
