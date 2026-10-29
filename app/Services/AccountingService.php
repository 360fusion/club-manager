<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\LedgerAuditLog;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    public const INDEPENDENT_EXAMINER_PURPOSE = 'independent_examiner_report';

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
        // One query for what exists, instead of a lookup for each of the default accounts on every call.
        $existing = Account::where('club_id', $club->id)->pluck('code')->all();

        foreach (self::DEFAULT_ACCOUNTS as $acc) {
            if (in_array($acc['code'], $existing, true)) {
                continue;
            }

            Account::firstOrCreate(
                ['club_id' => $club->id, 'code' => $acc['code']],
                ['name' => $acc['name'], 'type' => $acc['type'], 'currency' => $club->currencyCode(), 'is_active' => true]
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

        // Every line is rounded to the penny the way the ledger stores it, so what is checked is what is saved.
        $totalDebit = array_sum(array_map(fn ($i) => round((float) ($i['debit'] ?? 0), 2), $items));
        $totalCredit = array_sum(array_map(fn ($i) => round((float) ($i['credit'] ?? 0), 2), $items));

        if (round($totalDebit - $totalCredit, 2) !== 0.0) {
            throw new InvalidArgumentException("Journal entry is not balanced. Total Debits ({$club->currencySymbol()}{$totalDebit}) must equal Total Credits ({$club->currencySymbol()}{$totalCredit}).");
        }

        $entryYear = $club->financialYearFor($data['entry_date'] ?? now());
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
                    'debit' => round((float) ($item['debit'] ?? 0), 2),
                    'credit' => round((float) ($item['credit'] ?? 0), 2),
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
        $totalDebit = array_sum(array_map(fn ($i) => round((float) ($i['debit'] ?? 0), 2), $items));
        $totalCredit = array_sum(array_map(fn ($i) => round((float) ($i['credit'] ?? 0), 2), $items));

        if (round($totalDebit - $totalCredit, 2) !== 0.0) {
            throw new InvalidArgumentException('Journal entry must be balanced. Total Debit ('.Currencies::format($totalDebit, $club).') does not equal Total Credit ('.Currencies::format($totalCredit, $club).').');
        }

        $existingYear = $club->financialYearFor($entry->entry_date);
        $newYear = $club->financialYearFor($data['entry_date'] ?? $entry->entry_date);
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
                    'debit' => round((float) ($item['debit'] ?? 0), 2),
                    'credit' => round((float) ($item['credit'] ?? 0), 2),
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
     * Whether a bill/invoice of this amount must be held for a second admin's
     * sign-off before it can be posted (see Club::approvalThresholdSettings()).
     * Off by default, so a club that never sets a threshold sees no change.
     */
    public function amountRequiresApproval(Club $club, float $amount): bool
    {
        $threshold = $club->approvalThresholdSettings();

        return $threshold['enabled'] && $threshold['amount'] !== null && $amount >= (float) $threshold['amount'];
    }

    /**
     * The status a newly-issued (non-draft) bill/invoice should carry: 'pending_approval'
     * if it meets the club's approval threshold, else 'unpaid'. Callers only post the
     * issued journal entry when this returns 'unpaid' — a pending_approval item is not
     * on the ledger until AccountingService::approveBill()/approveInvoice() runs.
     */
    public function issuedStatusFor(Club $club, float $amount): string
    {
        return $this->amountRequiresApproval($club, $amount) ? DocumentStatus::PendingApproval->value : DocumentStatus::Unpaid->value;
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

            $status = ! empty($data['is_draft']) ? DocumentStatus::Draft->value : $this->issuedStatusFor($club, (float) $data['amount']);
            $vat = $this->resolveVatFields($club, (float) $data['amount'], isset($data['vat_rate']) ? (float) $data['vat_rate'] : null);

            $bill = Bill::create([
                'club_id' => $club->id,
                'created_by' => $data['created_by'] ?? auth()->id(),
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

            if ($status === DocumentStatus::Unpaid->value) {
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
     * Approve a bill that was held for a second sign-off (status pending_approval,
     * at or above the club's approval threshold): posts the issued journal entry
     * only now — it was deliberately skipped at creation — and logs the approval.
     * The approver must not be the person who raised it.
     */
    public function approveBill(Bill $bill, User $approver): void
    {
        if ($bill->status !== DocumentStatus::PendingApproval->value) {
            throw new InvalidArgumentException("Vendor bill {$bill->bill_number} is not awaiting approval.");
        }

        if ($bill->created_by !== null && (int) $bill->created_by === $approver->id) {
            throw new InvalidArgumentException('You raised this bill, so you cannot also approve it — ask another admin or treasurer.');
        }

        DB::transaction(function () use ($bill, $approver) {
            $bill->update(['status' => DocumentStatus::Unpaid->value]);

            $club = $bill->club;
            $this->postVendorBillIssuedJournal($club, $bill);
            $this->log($club, 'bill', $bill->id, 'approved', "Vendor bill {$bill->bill_number} ({$bill->vendor_name}) approved by {$approver->name} and posted to Accounts Payable.", userId: $approver->id);
        });
    }

    /**
     * Approve an invoice held for a second sign-off. See approveBill() — same
     * deferred-posting, different-approver semantics.
     */
    public function approveInvoice(Invoice $invoice, User $approver): void
    {
        if ($invoice->status !== DocumentStatus::PendingApproval->value) {
            throw new InvalidArgumentException("Invoice {$invoice->invoice_number} is not awaiting approval.");
        }

        if ($invoice->created_by !== null && (int) $invoice->created_by === $approver->id) {
            throw new InvalidArgumentException('You raised this invoice, so you cannot also approve it — ask another admin or treasurer.');
        }

        DB::transaction(function () use ($invoice, $approver) {
            $invoice->update(['status' => DocumentStatus::Unpaid->value]);

            $club = $invoice->club;
            $this->postMemberInvoiceIssuedJournal($club, $invoice);
            $this->log($club, 'invoice', $invoice->id, 'approved', "Invoice {$invoice->invoice_number} approved by {$approver->name} and posted to Accounts Receivable.", userId: $approver->id);
        });
    }

    /**
     * Mark a Vendor Bill as Paid & Post Settlement Journal. This is a manual/administrative
     * "we have settled this" declaration by a treasurer or admin — it is independent of
     * whether the payment has since been confirmed against a bank statement line, see
     * markBillReconciled().
     */
    public function markBillAsPaid(Bill $bill, ?User $actor = null): void
    {
        if ($bill->status === DocumentStatus::Paid->value) {
            return;
        }

        DB::transaction(function () use ($bill, $actor) {
            $bill->update([
                'status' => DocumentStatus::Paid->value,
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
        if ($invoice->status === DocumentStatus::Paid->value) {
            return;
        }

        DB::transaction(function () use ($invoice, $actor) {
            $invoice->update([
                'status' => DocumentStatus::Paid->value,
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
}
