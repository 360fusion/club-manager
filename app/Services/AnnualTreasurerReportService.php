<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Club;
use App\Services\Signatures\SignatureRequestService;
use App\Support\Csv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the annual Treasurer's Report a Lodge Secretary would otherwise build by
 * hand for the Installation meeting: a General Fund and Charity/Benevolent Fund
 * receipts & payments statement, bank reconciliation, and a membership/arrears
 * summary shaped for the Provincial/UGLE per-capita return.
 *
 * Accounts 4300 (Raffle & Charity Contributions), 4400 (Alms Collections) and 4500
 * (Donations & Bequests) are treated as Charity Fund income for this report; every
 * other revenue/expense account is General Fund.
 */
class AnnualTreasurerReportService
{
    private const CHARITY_LEDGER_CODES = ['4300', '4400', '4500'];

    public function __construct(
        protected AccountingService $accountingService,
        protected SubscriptionBillingService $subscriptionBillingService,
        protected SignatureRequestService $signatureRequestService,
    ) {}

    public function build(Club $club, int $year): array
    {
        $accounts = Account::where('club_id', $club->id)->whereIn('type', ['revenue', 'expense'])->get();

        $generalFund = $this->fundReceiptsAndPayments($club, $year, $accounts->reject(fn ($a) => in_array($a->code, self::CHARITY_LEDGER_CODES, true)));
        $ledgerCharityFund = $this->fundReceiptsAndPayments($club, $year, $accounts->filter(fn ($a) => in_array($a->code, self::CHARITY_LEDGER_CODES, true)));

        $collections = CharityCollection::where('club_id', $club->id)->whereYear('created_at', $year)->get();
        $grants = CharityGrant::where('club_id', $club->id)
            ->where('approval_status', GrantApprovalStatus::Disbursed)
            ->whereYear('updated_at', $year)
            ->get();

        $bankAccounts = BankAccount::where('club_id', $club->id)->with('account')->get();

        $arrears = $this->subscriptionBillingService->checkRule181Arrears($club);

        $auditSignOff = AccountingYearAudit::where('club_id', $club->id)->where('financial_year', $year)->with(['auditorOne', 'auditorTwo'])->first();

        return [
            'club_name' => $club->name,
            'financial_year' => $year,
            'is_closed' => $club->financialYearIsClosed($year),
            'general_fund' => $generalFund,
            'charity_fund' => [
                'ledger' => $ledgerCharityFund,
                'collections_total' => round((float) $collections->sum(fn ($c) => (float) $c->cash_amount + (float) $c->cheque_amount), 2),
                'collections_count' => $collections->count(),
                'grants_disbursed_total' => round((float) $grants->sum('amount'), 2),
                'grants_disbursed_count' => $grants->count(),
            ],
            'bank_accounts' => $bankAccounts->map(fn ($b) => [
                'bank_name' => $b->bank_name,
                'account_name' => $b->account_name,
                'opening_balance' => (float) $b->opening_balance,
                'closing_ledger_balance' => $b->ledger_balance,
                'closing_statement_balance' => $b->statement_balance,
            ])->values()->all(),
            'membership' => [
                'in_arrears_count' => $arrears->count(),
                'in_arrears' => $arrears->map(fn ($sub) => [
                    'member_name' => $sub->member?->full_name,
                    'invoice_reference' => $sub->invoice_reference,
                    'balance_due' => (float) $sub->balance_due,
                ])->values()->all(),
            ],
            'vat' => $club->vatIsEnabled() ? $this->accountingService->getVatReturnData($club, "{$year}-01-01") : null,
            'audit_sign_off' => $auditSignOff?->signed_off_at ? [
                'auditor_one' => $auditSignOff->auditorOne?->name,
                'auditor_two' => $auditSignOff->auditorTwo?->name,
                'signed_off_at' => $auditSignOff->signed_off_at->format('d M Y'),
                'notes' => $auditSignOff->notes,
            ] : null,
            'audit_signatures' => $auditSignOff && ! $auditSignOff->signed_off_at
                ? $this->signatureRequestService->forSignable($auditSignOff)
                    ->whereIn('purpose', ['year_audit_auditor_one', 'year_audit_auditor_two'])
                    ->unique('purpose')
                    ->mapWithKeys(fn ($r) => [$r->purpose => ['signer_name' => $r->signer_name, 'status' => $r->status->value, 'label' => $r->status->label()]])
                    ->all()
                : [],
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function fundReceiptsAndPayments(Club $club, int $year, $accounts): array
    {
        $rows = $accounts->map(function (Account $account) use ($year) {
            $actual = $this->actualForAccountAndYear($account, $year);

            return [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'amount' => $actual,
            ];
        })->filter(fn ($r) => $r['amount'] != 0.0)->values()->all();

        $income = array_sum(array_map(fn ($r) => $r['type'] === 'revenue' ? $r['amount'] : 0, $rows));
        $expenditure = array_sum(array_map(fn ($r) => $r['type'] === 'expense' ? $r['amount'] : 0, $rows));

        return [
            'rows' => $rows,
            'total_income' => round($income, 2),
            'total_expenditure' => round($expenditure, 2),
            'net_surplus' => round($income - $expenditure, 2),
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

    public function toCsv(array $report): string
    {
        $csv = Csv::line(['Annual Treasurer\'s Report', $report['club_name'], (string) $report['financial_year']]);
        $csv .= Csv::line([]);

        $csv .= Csv::line(['GENERAL FUND']);
        $csv .= Csv::line(['Code', 'Name', 'Amount']);
        foreach ($report['general_fund']['rows'] as $row) {
            $csv .= Csv::line([$row['code'], $row['name'], $row['amount']]);
        }
        $csv .= Csv::line(['Total Income', '', $report['general_fund']['total_income']]);
        $csv .= Csv::line(['Total Expenditure', '', $report['general_fund']['total_expenditure']]);
        $csv .= Csv::line(['Net Surplus', '', $report['general_fund']['net_surplus']]);
        $csv .= Csv::line([]);

        $csv .= Csv::line(['CHARITY / BENEVOLENT FUND']);
        $csv .= Csv::line(['Collections Total', $report['charity_fund']['collections_total']]);
        $csv .= Csv::line(['Grants Disbursed Total', $report['charity_fund']['grants_disbursed_total']]);
        $csv .= Csv::line([]);

        $csv .= Csv::line(['BANK ACCOUNTS']);
        $csv .= Csv::line(['Bank', 'Account', 'Opening Balance', 'Closing Ledger Balance', 'Closing Statement Balance']);
        foreach ($report['bank_accounts'] as $b) {
            $csv .= Csv::line([$b['bank_name'], $b['account_name'], $b['opening_balance'], $b['closing_ledger_balance'], $b['closing_statement_balance']]);
        }
        $csv .= Csv::line([]);

        $csv .= Csv::line(['MEMBERS IN ARREARS (Rule 181)']);
        $csv .= Csv::line(['Member', 'Invoice Reference', 'Balance Due']);
        foreach ($report['membership']['in_arrears'] as $m) {
            $csv .= Csv::line([$m['member_name'], $m['invoice_reference'], $m['balance_due']]);
        }

        return $csv;
    }
}
