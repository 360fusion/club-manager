<?php

namespace App\Services;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    /**
     * Default Chart of Accounts Template
     */
    public const DEFAULT_ACCOUNTS = [
        ['code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Petty Cash', 'type' => 'asset'],
        ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
        ['code' => '2100', 'name' => 'Unearned Dues Revenue', 'type' => 'liability'],
        ['code' => '3000', 'name' => 'Retained Earnings', 'type' => 'equity'],
        ['code' => '3100', 'name' => 'Member Reserves', 'type' => 'equity'],
        ['code' => '4000', 'name' => 'Membership Dues Income', 'type' => 'revenue'],
        ['code' => '4100', 'name' => 'Event Ticket Revenue', 'type' => 'revenue'],
        ['code' => '4200', 'name' => 'Bar & Dining Sales', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Facility & Clubhouse Maintenance', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Utilities', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'Catering & Food Supplies', 'type' => 'expense'],
        ['code' => '5300', 'name' => 'Administrative & Software Fees', 'type' => 'expense'],
    ];

    /**
     * Seed default Chart of Accounts for a club.
     */
    public function seedDefaultAccounts(Club $club): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $acc) {
            Account::firstOrCreate(
                [
                    'club_id' => $club->id,
                    'code' => $acc['code'],
                ],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'currency' => 'GBP',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get or create a specific account by code.
     */
    public function getAccount(Club $club, string $code): Account
    {
        $this->seedDefaultAccounts($club);

        $account = Account::where('club_id', $club->id)->where('code', $code)->first();

        if (!$account) {
            throw new InvalidArgumentException("Account with code {$code} not found for club.");
        }

        return $account;
    }

    /**
     * Post a balanced double-entry journal entry.
     */
    public function postJournalEntry(Club $club, array $data): JournalEntry
    {
        $items = $data['items'] ?? [];
        if (count($items) < 2) {
            throw new InvalidArgumentException("A journal entry must contain at least 2 line items.");
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $totalDebit += (float) ($item['debit'] ?? 0);
            $totalCredit += (float) ($item['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new InvalidArgumentException("Journal entry is not balanced. Total Debits (£{$totalDebit}) must equal Total Credits (£{$totalCredit}).");
        }

        return DB::transaction(function () use ($club, $data, $items) {
            $entryCount = JournalEntry::where('club_id', $club->id)->count() + 1;
            $ref = $data['reference_number'] ?? 'JE-' . date('Y') . '-' . str_pad((string) $entryCount, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'club_id' => $club->id,
                'reference_number' => $ref,
                'entry_date' => $data['entry_date'] ?? date('Y-m-d'),
                'description' => $data['description'] ?? 'General Journal Entry',
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => $data['status'] ?? 'posted',
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => (float) ($item['debit'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Record Member Dues Payment in Double-Entry Ledger
     */
    public function recordMemberDuesPayment(Club $club, float $amount, string $description, ?int $sourceId = null): JournalEntry
    {
        $bankAcc = $this->getAccount($club, '1000'); // Operating Bank Account
        $duesAcc = $this->getAccount($club, '4000'); // Membership Dues Income

        return $this->postJournalEntry($club, [
            'description' => $description,
            'source_type' => 'MemberDues',
            'source_id' => $sourceId,
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Dues Payment Received'],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => 'Membership Dues Revenue'],
            ],
        ]);
    }

    /**
     * Record Event Ticket Sale in Double-Entry Ledger
     */
    public function recordEventTicketSale(Club $club, float $amount, string $description, ?int $sourceId = null): JournalEntry
    {
        $bankAcc = $this->getAccount($club, '1000'); // Operating Bank Account
        $ticketAcc = $this->getAccount($club, '4100'); // Event Ticket Revenue

        return $this->postJournalEntry($club, [
            'description' => $description,
            'source_type' => 'EventRsvp',
            'source_id' => $sourceId,
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Event Ticket Payment'],
                ['account_id' => $ticketAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => 'Event Ticket Revenue'],
            ],
        ]);
    }

    /**
     * Create Vendor Bill (Accounts Payable) & Post to Ledger
     */
    public function createVendorBill(Club $club, array $data): Bill
    {
        return DB::transaction(function () use ($club, $data) {
            $billCount = Bill::where('club_id', $club->id)->count() + 1;
            $billNum = $data['bill_number'] ?? 'BILL-' . date('Y') . '-' . str_pad((string) $billCount, 4, '0', STR_PAD_LEFT);

            $bill = Bill::create([
                'club_id' => $club->id,
                'bill_number' => $billNum,
                'vendor_name' => $data['vendor_name'],
                'category' => $data['category'] ?? 'General Expense',
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            // Post Ledger: Debit Expense (5000), Credit Accounts Payable (2000)
            $expenseAcc = $this->getAccount($club, '5000');
            $apAcc = $this->getAccount($club, '2000');

            $this->postJournalEntry($club, [
                'description' => "Vendor Bill: {$bill->vendor_name} ({$bill->bill_number})",
                'source_type' => 'VendorBill',
                'source_id' => $bill->id,
                'items' => [
                    ['account_id' => $expenseAcc->id, 'debit' => $bill->amount, 'credit' => 0, 'memo' => $bill->category],
                    ['account_id' => $apAcc->id, 'debit' => 0, 'credit' => $bill->amount, 'memo' => 'Accounts Payable'],
                ],
            ]);

            return $bill;
        });
    }

    /**
     * Mark Vendor Bill as Paid & Post Settlement Journal
     */
    public function markBillAsPaid(Bill $bill): void
    {
        if ($bill->status === 'paid') return;

        DB::transaction(function () use ($bill) {
            $bill->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $club = $bill->club;
            $apAcc = $this->getAccount($club, '2000');
            $bankAcc = $this->getAccount($club, '1000');

            $this->postJournalEntry($club, [
                'description' => "Paid Vendor Bill: {$bill->vendor_name} ({$bill->bill_number})",
                'source_type' => 'VendorBillPayment',
                'source_id' => $bill->id,
                'items' => [
                    ['account_id' => $apAcc->id, 'debit' => $bill->amount, 'credit' => 0, 'memo' => 'Clear Accounts Payable'],
                    ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => $bill->amount, 'memo' => 'Operating Bank Settlement'],
                ],
            ]);
        });
    }

    /**
     * Get Overall Financial Summary KPIs
     */
    public function getFinancialSummary(Club $club): array
    {
        $this->seedDefaultAccounts($club);

        $accounts = Account::where('club_id', $club->id)->get();

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

        $unpaidBillsTotal = (float) Bill::where('club_id', $club->id)->where('status', 'unpaid')->sum('amount');
        $unpaidInvoicesTotal = (float) Invoice::where('club_id', $club->id)->where('status', 'unpaid')->sum('amount');

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
        $this->seedDefaultAccounts($club);
        $summary = $this->getFinancialSummary($club);
        $accounts = Account::where('club_id', $club->id)->orderBy('code')->get();

        // 1. Account Summary
        $accountSummary = $accounts->map(function ($acc) {
            $debits = (float) DB::table('accounting_journal_items')
                ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_items.journal_entry_id')
                ->where('accounting_journal_entries.club_id', $acc->club_id)
                ->where('accounting_journal_items.account_id', $acc->id)
                ->sum('debit');

            $credits = (float) DB::table('accounting_journal_items')
                ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_items.journal_entry_id')
                ->where('accounting_journal_entries.club_id', $acc->club_id)
                ->where('accounting_journal_items.account_id', $acc->id)
                ->sum('credit');

            return [
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'total_debit' => round($debits, 2),
                'total_credit' => round($credits, 2),
                'net_balance' => round($acc->balance, 2),
            ];
        });

        // 2. Aged Payables Summary
        $bills = Bill::where('club_id', $club->id)->where('status', 'unpaid')->get();
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
            ];
        }

        // 3. Aged Receivables Summary
        $invoices = Invoice::where('club_id', $club->id)->where('status', 'unpaid')->with('user')->get();
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
            ];
        }

        // 4. Balance Sheet Summary
        $balanceSheet = [
            'assets' => $accounts->where('type', 'asset')->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'liabilities' => $accounts->where('type', 'liability')->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'equity' => $accounts->where('type', 'equity')->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
            'total_assets' => $summary['total_assets'],
            'total_liabilities' => $summary['total_liabilities'],
            'total_equity' => $summary['total_equity'],
        ];

        // 5. Cash Summary
        $cashAccounts = $accounts->whereIn('code', ['1000', '1100']);
        $cashSummary = [
            'total_cash_on_hand' => round($cashAccounts->sum('balance'), 2),
            'accounts' => $cashAccounts->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'balance' => round($a->balance, 2)]),
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
            'revenues' => $accounts->where('type', 'revenue')->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'amount' => round($a->balance, 2)]),
            'expenses' => $accounts->where('type', 'expense')->values()->map(fn($a) => ['code' => $a->code, 'name' => $a->name, 'amount' => round($a->balance, 2)]),
            'total_revenue' => $summary['total_revenue'],
            'total_expenses' => $summary['total_expenses'],
            'net_income' => $summary['net_income'],
        ];

        return [
            'account_summary' => $accountSummary,
            'aged_payables' => $agedPayables,
            'aged_receivables' => $agedReceivables,
            'balance_sheet' => $balanceSheet,
            'cash_summary' => $cashSummary,
            'executive_summary' => $execSummary,
            'profit_and_loss' => $profitAndLoss,
        ];
    }
}
