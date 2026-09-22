<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriodClose;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\LedgerAuditLog;
use App\Models\Accounting\MeetingFinancialReturn;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Csv;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        ['code' => '4300', 'name' => 'Raffle & Charity Contributions', 'type' => 'revenue'],
        ['code' => '4400', 'name' => 'Alms Collections', 'type' => 'revenue'],
        ['code' => '4500', 'name' => 'Donations & Bequests', 'type' => 'revenue'],
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
                    'currency' => $club->currencyCode(),
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get or create the club's VAT Control Account (2200) — the single net account
     * both input VAT (on bills) and output VAT (on invoices) post against. Not part
     * of DEFAULT_ACCOUNTS since most clubs never register for VAT; created lazily
     * the first time a VAT-enabled club posts a VAT-inclusive bill or invoice.
     */
    public function getOrCreateVatControlAccount(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '2200'],
            ['name' => 'VAT Control Account', 'type' => 'liability', 'currency' => $club->currencyCode(), 'is_active' => true]
        );
    }

    /**
     * Split a gross (VAT-inclusive) amount into net + VAT for a club, if that club has
     * VAT enabled — otherwise every VAT field is null and the gross amount is returned
     * unchanged, so a club that never turns VAT on sees no change in behaviour at all.
     *
     * @return array{amount: float, net_amount: ?float, vat_rate: ?float, vat_amount: ?float}
     */
    public function resolveVatFields(Club $club, float $grossAmount, ?float $vatRateOverride = null): array
    {
        $gross = round($grossAmount, 2);

        if (! $club->vatIsEnabled()) {
            return ['amount' => $gross, 'net_amount' => null, 'vat_rate' => null, 'vat_amount' => null];
        }

        $rate = $vatRateOverride ?? (float) $club->vatSettings()['default_rate'];

        if ($rate <= 0) {
            return ['amount' => $gross, 'net_amount' => $gross, 'vat_rate' => 0.0, 'vat_amount' => 0.0];
        }

        $net = round($gross / (1 + $rate / 100), 2);
        $vat = round($gross - $net, 2);

        return ['amount' => $gross, 'net_amount' => $net, 'vat_rate' => $rate, 'vat_amount' => $vat];
    }

    /**
     * Get or create a specific account by code.
     */
    public function getAccount(Club $club, string $code): Account
    {
        $this->seedDefaultAccounts($club);

        $account = Account::where('club_id', $club->id)->where('code', $code)->first();

        if (! $account) {
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
            throw new InvalidArgumentException('A journal entry must contain at least 2 line items.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $totalDebit += (float) ($item['debit'] ?? 0);
            $totalCredit += (float) ($item['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new InvalidArgumentException("Journal entry is not balanced. Total Debits ({$club->currencySymbol()}{$totalDebit}) must equal Total Credits ({$club->currencySymbol()}{$totalCredit}).");
        }

        $entryYear = (int) date('Y', strtotime($data['entry_date'] ?? date('Y-m-d')));
        if ($club->financialYearIsClosed($entryYear) && ! (auth()->user()?->is_super_admin)) {
            throw new InvalidArgumentException("The books for {$entryYear} are closed. Reopen the financial year before posting to it.");
        }

        return DB::transaction(function () use ($club, $data, $items) {
            $entryCount = JournalEntry::where('club_id', $club->id)->count() + 1;
            $ref = $data['reference_number'] ?? 'JE-'.date('Y').'-'.str_pad((string) $entryCount, 4, '0', STR_PAD_LEFT);

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

            $this->log($club, 'journal_entry', $entry->id, 'created', $entry->description, userId: $entry->created_by);

            return $entry;
        });
    }

    /**
     * Update an existing Journal Entry & recalculate balance
     */
    public function updateJournalEntry(Club $club, int $entryId, array $data): JournalEntry
    {
        $entry = JournalEntry::where('club_id', $club->id)->where('id', $entryId)->firstOrFail();

        $items = $data['items'] ?? [];
        $totalDebit = array_reduce($items, fn ($sum, $i) => $sum + (float) ($i['debit'] ?? 0), 0.0);
        $totalCredit = array_reduce($items, fn ($sum, $i) => $sum + (float) ($i['credit'] ?? 0), 0.0);

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new InvalidArgumentException('Journal entry must be balanced. Total Debit ('.Currencies::format($totalDebit, $club).') does not equal Total Credit ('.Currencies::format($totalCredit, $club).').');
        }

        $existingYear = (int) $entry->entry_date->format('Y');
        $newYear = (int) date('Y', strtotime($data['entry_date'] ?? $entry->entry_date->format('Y-m-d')));
        if ((! (auth()->user()?->is_super_admin)) && ($club->financialYearIsClosed($existingYear) || $club->financialYearIsClosed($newYear))) {
            throw new InvalidArgumentException("The books for {$existingYear} are closed. Reopen the financial year before editing this entry.");
        }

        $beforeItems = $entry->items()->get(['account_id', 'debit', 'credit', 'memo'])->toArray();

        return DB::transaction(function () use ($club, $entry, $data, $items, $beforeItems) {
            $entry->update([
                'entry_date' => $data['entry_date'] ?? $entry->entry_date,
                'description' => $data['description'] ?? $entry->description,
            ]);

            $entry->items()->delete();

            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => (float) ($item['debit'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            $this->log($club, 'journal_entry', $entry->id, 'updated', "Journal entry edited: {$entry->description}", before: $beforeItems, after: $items);

            return $entry;
        });
    }

    /**
     * Write an entry to the append-only ledger audit trail (App\Models\Accounting\LedgerAuditLog).
     * The single writer for every mutation this service (and its sibling services)
     * makes to a financial record.
     */
    public function log(Club $club, string $entityType, int $entityId, string $action, string $summary, ?array $before = null, ?array $after = null, ?int $userId = null): void
    {
        LedgerAuditLog::create([
            'club_id' => $club->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => $userId ?? auth()->id(),
            'summary' => $summary,
            'before_json' => $before,
            'after_json' => $after,
        ]);
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
    public function createVendorBill(Club $club, array $data, $attachmentFile = null): Bill
    {
        return DB::transaction(function () use ($club, $data, $attachmentFile) {
            $billCount = Bill::where('club_id', $club->id)->count() + 1;
            $billNum = $data['bill_number'] ?? 'BILL-'.date('Y').'-'.str_pad((string) $billCount, 4, '0', STR_PAD_LEFT);

            $mediaId = null;
            if ($attachmentFile && $attachmentFile->isValid()) {
                $media = $club->addMedia($attachmentFile)
                    ->withCustomProperties([
                        'is_accounting_protected' => true,
                        'source' => 'accounting',
                        'bill_number' => $billNum,
                        'vendor_name' => $data['vendor_name'],
                    ])
                    ->toMediaCollection('accounting', 'local');
                $mediaId = $media->id;
            }

            $status = ! empty($data['is_draft']) ? 'draft' : 'unpaid';
            $vat = $this->resolveVatFields($club, (float) $data['amount'], isset($data['vat_rate']) ? (float) $data['vat_rate'] : null);

            $bill = Bill::create([
                'club_id' => $club->id,
                'bill_number' => $billNum,
                'vendor_name' => $data['vendor_name'],
                'category' => $data['category'] ?? 'General Expense',
                'amount' => $vat['amount'],
                'net_amount' => $vat['net_amount'],
                'vat_rate' => $vat['vat_rate'],
                'vat_amount' => $vat['vat_amount'],
                'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'media_id' => $mediaId,
            ]);

            if ($status !== 'draft') {
                $this->postVendorBillIssuedJournal($club, $bill);
            }

            return $bill;
        });
    }

    /**
     * Post the "bill issued" ledger entry (Debit Expense, [Debit VAT Control], Credit
     * Accounts Payable) — shared by createVendorBill() and by publishing/editing a
     * draft bill into 'unpaid', so VAT is only ever computed once, at creation time.
     */
    public function postVendorBillIssuedJournal(Club $club, Bill $bill): void
    {
        $expenseAcc = $this->getAccount($club, '5000');
        $apAcc = $this->getAccount($club, '2000');

        $items = [
            ['account_id' => $expenseAcc->id, 'debit' => $bill->net_amount ?? $bill->amount, 'credit' => 0, 'memo' => $bill->category],
        ];

        if ($bill->hasVat() && (float) $bill->vat_amount > 0) {
            $vatAcc = $this->getOrCreateVatControlAccount($club);
            $items[] = ['account_id' => $vatAcc->id, 'debit' => $bill->vat_amount, 'credit' => 0, 'memo' => 'Input VAT'];
        }

        $items[] = ['account_id' => $apAcc->id, 'debit' => 0, 'credit' => $bill->amount, 'memo' => 'Accounts Payable'];

        $this->postJournalEntry($club, [
            'description' => "Vendor Bill: {$bill->vendor_name} ({$bill->bill_number})",
            'source_type' => 'VendorBill',
            'source_id' => $bill->id,
            'items' => $items,
        ]);
    }

    /**
     * Post the "invoice issued" ledger entry (Debit Accounts Receivable, Credit
     * Membership Income, [Credit VAT Control]) — shared by storeInvoice()/publishInvoice()
     * on the controller side, so VAT is only ever computed once, at creation time.
     */
    public function postMemberInvoiceIssuedJournal(Club $club, Invoice $invoice): void
    {
        $arAcc = $this->getAccount($club, '1200');
        $duesAcc = $this->getAccount($club, '4000');

        $items = [
            ['account_id' => $arAcc->id, 'debit' => $invoice->amount, 'credit' => 0, 'memo' => 'Accounts Receivable'],
            ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => $invoice->net_amount ?? $invoice->amount, 'memo' => 'Membership Income'],
        ];

        if ($invoice->hasVat() && (float) $invoice->vat_amount > 0) {
            $vatAcc = $this->getOrCreateVatControlAccount($club);
            $items[] = ['account_id' => $vatAcc->id, 'debit' => 0, 'credit' => $invoice->vat_amount, 'memo' => 'Output VAT'];
        }

        $this->postJournalEntry($club, [
            'description' => "Member Invoice Issued: {$invoice->title} ({$invoice->invoice_number})",
            'source_type' => 'Invoice',
            'source_id' => $invoice->id,
            'items' => $items,
        ]);
    }

    /**
     * Mark a Vendor Bill as Paid & Post Settlement Journal. This is a manual/administrative
     * "we have settled this" declaration by a treasurer or admin — it is independent of
     * whether the payment has since been confirmed against a bank statement line, see
     * markBillReconciled().
     */
    public function markBillAsPaid(Bill $bill, ?User $actor = null): void
    {
        if ($bill->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($bill, $actor) {
            $bill->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $actor?->id,
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

            $this->log($club, 'bill', $bill->id, 'paid', "Vendor bill {$bill->bill_number} ({$bill->vendor_name}) marked paid.", userId: $actor?->id);
        });
    }

    /**
     * Mark a member Invoice as Paid & Post Settlement Journal. See markBillAsPaid() —
     * same "manual declaration, independent of bank reconciliation" semantics.
     */
    public function markInvoiceAsPaid(Invoice $invoice, ?User $actor = null): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($invoice, $actor) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $actor?->id,
            ]);

            $club = $invoice->club;
            $arAcc = $this->getAccount($club, '1200');
            $bankAcc = $this->getAccount($club, '1000');

            $this->postJournalEntry($club, [
                'description' => "Invoice Paid: {$invoice->title} ({$invoice->invoice_number})",
                'source_type' => 'InvoicePayment',
                'source_id' => $invoice->id,
                'items' => [
                    ['account_id' => $bankAcc->id, 'debit' => $invoice->amount, 'credit' => 0, 'memo' => 'Operating Bank Deposit'],
                    ['account_id' => $arAcc->id, 'debit' => 0, 'credit' => $invoice->amount, 'memo' => 'Clear Accounts Receivable'],
                ],
            ]);

            $this->log($club, 'invoice', $invoice->id, 'paid', "Invoice {$invoice->invoice_number} ({$invoice->title}) marked paid.", userId: $actor?->id);
        });
    }

    /**
     * Stamp a Bill as confirmed against an actual bank statement line. Does not post
     * a journal entry itself (the bank reconciliation flow posts its own entry) and
     * does not require the bill to already be marked paid — reconciling can be what
     * triggers payment, or can confirm a payment recorded earlier by other means.
     */
    public function markBillReconciled(Bill $bill, BankTransaction $transaction): void
    {
        $bill->update([
            'reconciled_at' => now(),
            'reconciled_bank_transaction_id' => $transaction->id,
        ]);

        $this->log($bill->club, 'bill', $bill->id, 'reconciled', "Vendor bill {$bill->bill_number} reconciled against bank transaction #{$transaction->id}.");
    }

    /**
     * Stamp an Invoice as confirmed against an actual bank statement line. See
     * markBillReconciled().
     */
    public function markInvoiceReconciled(Invoice $invoice, BankTransaction $transaction): void
    {
        $invoice->update([
            'reconciled_at' => now(),
            'reconciled_bank_transaction_id' => $transaction->id,
        ]);

        $this->log($invoice->club, 'invoice', $invoice->id, 'reconciled', "Invoice {$invoice->invoice_number} reconciled against bank transaction #{$transaction->id}.");
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
                ->where('accounting_journal_entries.status', '!=', 'void')
                ->sum('debit');

            $credits = (float) DB::table('accounting_journal_items')
                ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_items.journal_entry_id')
                ->where('accounting_journal_entries.club_id', $acc->club_id)
                ->where('accounting_journal_items.account_id', $acc->id)
                ->where('accounting_journal_entries.status', '!=', 'void')
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
     * Post Meeting Financial Return (Dining Calculator + Charity Collections) to Accounting Ledger
     */
    public function postMeetingFinancialReturn(Club $club, Meeting $meeting, array $data): MeetingFinancialReturn
    {
        return DB::transaction(function () use ($club, $meeting, $data) {
            $diningFee = (float) ($data['dining_fee_per_head'] ?? 0);
            $paidDiners = (int) ($data['paid_diners_count'] ?? 0);
            $waivedDiners = (int) ($data['waived_diners_count'] ?? 0);
            $kitchenCostPerHead = (float) ($data['kitchen_cost_per_head'] ?? 0);
            $kitchenVendor = ! empty($data['kitchen_vendor_name']) ? $data['kitchen_vendor_name'] : 'Kitchen Caterer';

            $raffleAmount = (float) ($data['raffle_amount'] ?? 0);
            $almsAmount = (float) ($data['alms_amount'] ?? 0);
            $donationsAmount = (float) ($data['donations_amount'] ?? 0);
            $bequestAmount = (float) ($data['bequest_amount'] ?? 0);

            $totalMeals = $paidDiners + $waivedDiners;
            $totalDiningRevenue = round($paidDiners * $diningFee, 2);
            $totalKitchenBill = round($totalMeals * $kitchenCostPerHead, 2);
            $netDiningSurplus = round($totalDiningRevenue - $totalKitchenBill, 2);
            $totalCharity = round($raffleAmount + $almsAmount + $donationsAmount + $bequestAmount, 2);
            $netBankDeposit = round($totalDiningRevenue + $totalCharity, 2);

            $returnDate = ! empty($meeting->meeting_date) ? date('Y-m-d', strtotime((string) $meeting->meeting_date)) : date('Y-m-d');

            // 1. Create Kitchen Vendor Bill (A/P)
            $vendorBill = null;
            if ($totalKitchenBill > 0) {
                $vendorBill = $this->createVendorBill($club, [
                    'vendor_name' => $kitchenVendor,
                    'category' => 'Catering & Food Supplies',
                    'amount' => $totalKitchenBill,
                    'due_date' => $returnDate,
                    'notes' => "Kitchen catering for {$meeting->title} ({$totalMeals} meals @ {$club->currencySymbol()}{$kitchenCostPerHead}/head)",
                ]);
            }

            // 2. Post Journal Entry to General Ledger
            $journalItems = [];
            $bankAcc = $this->getAccount($club, '1000');
            $diningRevenueAcc = $this->getAccount($club, '4200');
            $raffleAcc = $this->getAccount($club, '4300');
            $almsAcc = $this->getAccount($club, '4400');
            $donationsAcc = $this->getAccount($club, '4500');

            if ($netBankDeposit > 0) {
                $journalItems[] = ['account_id' => $bankAcc->id, 'debit' => $netBankDeposit, 'credit' => 0, 'memo' => "Meeting Collections Deposit ({$meeting->title})"];
            }

            if ($totalDiningRevenue > 0) {
                $journalItems[] = ['account_id' => $diningRevenueAcc->id, 'debit' => 0, 'credit' => $totalDiningRevenue, 'memo' => "Dining Receipts ({$paidDiners} paid diners)"];
            }
            if ($raffleAmount > 0) {
                $journalItems[] = ['account_id' => $raffleAcc->id, 'debit' => 0, 'credit' => $raffleAmount, 'memo' => 'Raffle Ticket Sales'];
            }
            if ($almsAmount > 0) {
                $journalItems[] = ['account_id' => $almsAcc->id, 'debit' => 0, 'credit' => $almsAmount, 'memo' => 'Alms Box Collection'];
            }
            if ($donationsAmount > 0) {
                $journalItems[] = ['account_id' => $donationsAcc->id, 'debit' => 0, 'credit' => $donationsAmount, 'memo' => 'Meeting Donations'];
            }
            if ($bequestAmount > 0) {
                $journalItems[] = ['account_id' => $donationsAcc->id, 'debit' => 0, 'credit' => $bequestAmount, 'memo' => 'Bequest Funds'];
            }

            $journalEntry = null;
            if (count($journalItems) >= 2) {
                $journalEntry = $this->postJournalEntry($club, [
                    'description' => "Meeting Financial Return: {$meeting->title} ({$returnDate})",
                    'source_type' => 'MeetingReturn',
                    'source_id' => $meeting->id,
                    'items' => $journalItems,
                ]);
            }

            // 3. Store MeetingFinancialReturn Record
            $financialReturn = MeetingFinancialReturn::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'meeting_id' => $meeting->id,
                ],
                [
                    'return_date' => $returnDate,
                    'dining_fee_per_head' => $diningFee,
                    'paid_diners_count' => $paidDiners,
                    'waived_diners_count' => $waivedDiners,
                    'waived_reason' => $data['waived_reason'] ?? null,
                    'kitchen_cost_per_head' => $kitchenCostPerHead,
                    'kitchen_vendor_name' => $kitchenVendor,
                    'raffle_amount' => $raffleAmount,
                    'alms_amount' => $almsAmount,
                    'donations_amount' => $donationsAmount,
                    'bequest_amount' => $bequestAmount,
                    'total_dining_revenue' => $totalDiningRevenue,
                    'total_kitchen_bill' => $totalKitchenBill,
                    'net_dining_surplus' => $netDiningSurplus,
                    'total_charity_collected' => $totalCharity,
                    'net_bank_deposit' => $netBankDeposit,
                    'vendor_bill_id' => $vendorBill?->id,
                    'journal_entry_id' => $journalEntry?->id,
                    'is_draft' => false,
                    'notes' => $data['notes'] ?? null,
                ]
            );

            return $financialReturn;
        });
    }

    /**
     * Save draft financial return for a meeting without posting to general ledger.
     */
    public function saveMeetingFinancialReturnDraft(Club $club, Meeting $meeting, array $data): MeetingFinancialReturn
    {
        $diningFee = (float) ($data['dining_fee_per_head'] ?? 0);
        $paidDiners = (int) ($data['paid_diners_count'] ?? 0);
        $waivedDiners = (int) ($data['waived_diners_count'] ?? 0);
        $kitchenCostPerHead = (float) ($data['kitchen_cost_per_head'] ?? 0);
        $kitchenVendor = ! empty($data['kitchen_vendor_name']) ? $data['kitchen_vendor_name'] : 'Kitchen Caterer';

        $raffleAmount = (float) ($data['raffle_amount'] ?? 0);
        $almsAmount = (float) ($data['alms_amount'] ?? 0);
        $donationsAmount = (float) ($data['donations_amount'] ?? 0);
        $bequestAmount = (float) ($data['bequest_amount'] ?? 0);

        $totalMeals = $paidDiners + $waivedDiners;
        $totalDiningRevenue = round($paidDiners * $diningFee, 2);
        $totalKitchenBill = round($totalMeals * $kitchenCostPerHead, 2);
        $netDiningSurplus = round($totalDiningRevenue - $totalKitchenBill, 2);
        $totalCharity = round($raffleAmount + $almsAmount + $donationsAmount + $bequestAmount, 2);
        $netBankDeposit = round($totalDiningRevenue + $totalCharity, 2);

        $returnDate = ! empty($data['return_date'])
            ? date('Y-m-d', strtotime((string) $data['return_date']))
            : (! empty($meeting->meeting_date) ? date('Y-m-d', strtotime((string) $meeting->meeting_date)) : date('Y-m-d'));

        return MeetingFinancialReturn::updateOrCreate(
            [
                'club_id' => $club->id,
                'meeting_id' => $meeting->id,
            ],
            [
                'return_date' => $returnDate,
                'dining_fee_per_head' => $diningFee,
                'paid_diners_count' => $paidDiners,
                'waived_diners_count' => $waivedDiners,
                'waived_reason' => $data['waived_reason'] ?? null,
                'kitchen_cost_per_head' => $kitchenCostPerHead,
                'kitchen_vendor_name' => $kitchenVendor,
                'raffle_amount' => $raffleAmount,
                'alms_amount' => $almsAmount,
                'donations_amount' => $donationsAmount,
                'bequest_amount' => $bequestAmount,
                'total_dining_revenue' => $totalDiningRevenue,
                'total_kitchen_bill' => $totalKitchenBill,
                'net_dining_surplus' => $netDiningSurplus,
                'total_charity_collected' => $totalCharity,
                'net_bank_deposit' => $netBankDeposit,
                'is_draft' => true,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    /**
     * Build 4-Column Comparative Annual Income & Expenditure Statement from real ledger,
     * bill/invoice and meeting-return data for the current and prior calendar year. No
     * category ever falls back to a placeholder figure — a category with no activity
     * in a year is reported as 0.
     */
    public function getComparativeIncomeExpenditureData(Club $club): array
    {
        $currentYear = (int) now()->year;
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
        $subsIncome = (float) Invoice::where('club_id', $club->id)
            ->where('status', 'paid')
            ->whereYear('paid_at', $year)
            ->sum('amount');

        $subsExpenditure = (float) Bill::where('club_id', $club->id)
            ->where('status', 'paid')
            ->whereYear('paid_at', $year)
            ->sum('amount');

        $returns = MeetingFinancialReturn::where('club_id', $club->id)
            ->where('is_draft', false)
            ->whereYear('return_date', $year)
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
     * Set opening balance / carry-over amount for an account.
     */
    public function setOpeningBalance(Club $club, Account $account, float $amount, ?string $asOfDate = null): ?JournalEntry
    {
        if ($amount == 0) {
            return null;
        }

        $retainedEarningsAcc = $this->getAccount($club, '3000');
        $asOfDate = $asOfDate ?: date('Y-01-01');

        $isDebitAccount = in_array($account->type, ['asset', 'expense']);

        $items = [];
        if ($isDebitAccount) {
            $items[] = ['account_id' => $account->id, 'debit' => abs($amount), 'credit' => 0, 'memo' => "Opening Carry-Over Balance ({$account->code})"];
            $items[] = ['account_id' => $retainedEarningsAcc->id, 'debit' => 0, 'credit' => abs($amount), 'memo' => 'Prior Year Retained Reserves'];
        } else {
            $items[] = ['account_id' => $retainedEarningsAcc->id, 'debit' => abs($amount), 'credit' => 0, 'memo' => 'Prior Year Retained Reserves'];
            $items[] = ['account_id' => $account->id, 'debit' => 0, 'credit' => abs($amount), 'memo' => "Opening Carry-Over Balance ({$account->code})"];
        }

        return $this->postJournalEntry($club, [
            'entry_date' => $asOfDate,
            'description' => "Opening Balance / Carry Over: {$account->code} - {$account->name}",
            'source_type' => 'OpeningBalance',
            'source_id' => $account->id,
            'items' => $items,
        ]);
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
     * Close a financial year's books, blocking further postings into it for normal
     * admins (see financialYearIsClosed() usage in postJournalEntry()/updateJournalEntry()).
     */
    public function closeFinancialYear(Club $club, int $year, ?User $actor = null, ?string $notes = null): AccountingPeriodClose
    {
        $close = AccountingPeriodClose::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            ['closed_at' => now(), 'closed_by_user_id' => $actor?->id, 'notes' => $notes]
        );

        $this->log($club, 'financial_year', $year, 'closed', "Financial year {$year} closed.", userId: $actor?->id);

        return $close;
    }

    /**
     * Reopen a previously closed financial year. Super-admin only at the controller
     * level — reopening the books is a rare, deliberate action.
     */
    public function reopenFinancialYear(Club $club, int $year, ?User $actor = null): void
    {
        AccountingPeriodClose::where('club_id', $club->id)->where('financial_year', $year)->delete();

        $this->log($club, 'financial_year', $year, 'reopened', "Financial year {$year} reopened.", userId: $actor?->id);
    }

    /**
     * Name the two elected auditors for a financial year and leave the audit awaiting their actual signatures
     * (signed_off_at stays null until both have signed via the signature-request flow).
     */
    public function requestYearAudit(Club $club, int $year, User $auditorOne, User $auditorTwo, ?string $notes = null): AccountingYearAudit
    {
        if ($auditorOne->is($auditorTwo)) {
            throw new InvalidArgumentException('The two auditors must be different people.');
        }

        return AccountingYearAudit::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            [
                'auditor_one_user_id' => $auditorOne->id,
                'auditor_two_user_id' => $auditorTwo->id,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Record that two elected auditors (Book of Constitutions Rule 153 — never the
     * Treasurer or Secretary) have signed off a financial year's accounts.
     */
    public function signOffYearAudit(Club $club, int $year, User $auditorOne, User $auditorTwo, ?string $notes = null): AccountingYearAudit
    {
        if ($auditorOne->is($auditorTwo)) {
            throw new InvalidArgumentException('The two auditors must be different people.');
        }

        $audit = AccountingYearAudit::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            [
                'auditor_one_user_id' => $auditorOne->id,
                'auditor_two_user_id' => $auditorTwo->id,
                'signed_off_at' => now(),
                'notes' => $notes,
            ]
        );

        $this->log($club, 'financial_year', $year, 'audited', "Financial year {$year} signed off by {$auditorOne->name} and {$auditorTwo->name}.");

        return $audit;
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
                collect(app(BudgetService::class)->getBudgetVsActual($club, $budgetYear ?? (int) now()->year)['rows'])
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
                ['Reference', 'Name', 'Days Overdue', 'Bucket', 'Amount'],
                collect($report['items'])->map(fn ($r) => [$r['bill_number'] ?? $r['invoice_number'] ?? '', $r['vendor_name'] ?? $r['recipient_name'] ?? '', $r['days_overdue'], $r['bucket'], $r['amount']])
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
            'comparative_income_expenditure' => $this->csvFromRows(
                ['Category', "Prior Income ({$report['prior_year_label']})", "Prior Expenditure ({$report['prior_year_label']})", "Current Income ({$report['current_year_label']})", "Current Expenditure ({$report['current_year_label']})"],
                collect($report['rows'])->map(fn ($r) => [$r['category'], $r['prior_income'], $r['prior_expenditure'], $r['current_income'], $r['current_expenditure']])
            ),
            default => throw new InvalidArgumentException("No CSV export defined for report: {$reportKey}"),
        };
    }

    /**
     * @param  array<int, string>  $headers
     * @param  Collection<int, array>  $rows
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
