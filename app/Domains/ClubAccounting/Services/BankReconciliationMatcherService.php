<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Services\AccountingService;
use App\Services\Events\EventPaymentService;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BankReconciliationMatcherService
{
    protected SubscriptionBillingService $billingService;

    public function __construct(SubscriptionBillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Analyze a staged bank transaction line and return ranked candidate matches with confidence scores.
     */
    public function suggestMatches(BankTransaction $transaction): array
    {
        $clubId = $transaction->club_id;
        $club = Club::with('province.grandLodge')->find($clubId);
        $desc = strtolower($transaction->raw_description);
        $ref = strtolower($transaction->reference ?? '');
        $amount = (float) $transaction->amount;
        $matches = [];

        // 1. Incoming Credits (+ amount) -> Match against Unpaid Member Subscriptions or Charity Relief
        if ($amount > 0) {
            // Check for Charity Relief references first
            if (preg_match('/(charity|relief|tlc|ben|donation|chest|l\d+)/i', $transaction->raw_description)) {
                $matches[] = [
                    'match_type' => 'charity_relief',
                    'target_id' => 0,
                    'target_title' => 'Charity Relief Chest / Provincial Contribution',
                    'target_amount' => $amount,
                    'confidence_score' => 85,
                    'confidence_level' => 'medium',
                    'match_reason' => 'Charity Keyword or Relief Chest Reference Pattern',
                    'record' => null,
                ];
            }

            $unpaidSubs = MemberSubscription::where('club_id', $clubId)
                ->unpaid()
                ->with(['member', 'tier'])
                ->get();

            foreach ($unpaidSubs as $sub) {
                $member = $sub->member;
                if (! $member) {
                    continue;
                }

                $invRef = strtolower($sub->invoice_reference ?? '');
                $lastName = strtolower($member->last_name);
                $firstName = strtolower($member->first_name);
                $email = strtolower($member->email ?? '');
                $duesAmount = (float) $sub->balance_due;

                $score = 0;
                $reason = '';

                // Rule A: Exact Invoice Ref match (100%)
                if ($invRef && (str_contains($desc, $invRef) || str_contains($ref, $invRef))) {
                    $score = 100;
                    $reason = "Exact Invoice Ref Match ({$sub->invoice_reference})";
                }
                // Rule B: Name & Exact Dues Amount Match (95%)
                elseif (($lastName && str_contains($desc, $lastName)) && abs($duesAmount - $amount) < 0.01) {
                    $score = 95;
                    $reason = "Member Name ({$member->last_name}) & Dues Amount (".Currencies::format($duesAmount, $club).') Match';
                }
                // Rule C: First Name & Last Name in description (85%)
                elseif ($lastName && $firstName && str_contains($desc, $lastName) && str_contains($desc, $firstName)) {
                    $score = 85;
                    $reason = "Full Member Name Match ({$member->full_name})";
                }
                // Rule D: Last Name or Email in description (75%)
                elseif (($lastName && str_contains($desc, $lastName)) || ($email && str_contains($desc, $email))) {
                    $score = 75;
                    $reason = "Member Surname/Email Match ({$member->last_name})";
                }
                // Rule E: Dues Amount Match alone (60%)
                elseif (abs($duesAmount - $amount) < 0.01) {
                    $score = 60;
                    $reason = 'Dues Amount Match ('.Currencies::format($duesAmount, $club).')';
                }

                if ($score > 0) {
                    $matches[] = [
                        'match_type' => 'member_subscription',
                        'target_id' => $sub->id,
                        'target_title' => "{$member->formatted_rank_name} — Invoice {$sub->invoice_reference}",
                        'target_amount' => $duesAmount,
                        'confidence_score' => $score,
                        'confidence_level' => $score >= 90 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
                        'match_reason' => $reason,
                        'record' => $sub,
                    ];
                }
            }

            // Unpaid member invoices (the Inertia accounting layer's Invoice model — distinct
            // from MemberSubscription above, e.g. one-off charges like locker rental).
            $unpaidInvoices = Invoice::where('club_id', $clubId)
                ->where('status', 'unpaid')
                ->with('user')
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                $invNo = strtolower($invoice->invoice_number ?? '');
                $invAmount = (float) $invoice->amount;
                $payerName = $invoice->user ? strtolower($invoice->user->name) : '';
                $payerEmail = $invoice->user ? strtolower($invoice->user->email ?? '') : '';

                $score = 0;
                $reason = '';

                // Rule A: Exact invoice number match (100%)
                if ($invNo && (str_contains($desc, $invNo) || str_contains($ref, $invNo))) {
                    $score = 100;
                    $reason = "Exact Invoice Number Match ({$invoice->invoice_number})";
                }
                // Rule B: Payer name & exact amount match (95%)
                elseif ($payerName && str_contains($desc, $payerName) && abs($invAmount - $amount) < 0.01) {
                    $score = 95;
                    $reason = "Payer Name ({$invoice->user->name}) & Invoice Amount (".Currencies::format($invAmount, $club).') Match';
                }
                // Rule C: Payer name or email in description (75%)
                elseif (($payerName && str_contains($desc, $payerName)) || ($payerEmail && str_contains($desc, $payerEmail))) {
                    $score = 75;
                    $reason = 'Payer Name/Email Match';
                }
                // Rule D: Invoice amount match alone (60%)
                elseif (abs($invAmount - $amount) < 0.01) {
                    $score = 60;
                    $reason = 'Invoice Amount Match ('.Currencies::format($invAmount, $club).')';
                }

                if ($score > 0) {
                    $matches[] = [
                        'match_type' => 'invoice',
                        'target_id' => $invoice->id,
                        'target_title' => "Invoice {$invoice->invoice_number} — {$invoice->title}",
                        'target_amount' => $invAmount,
                        'confidence_score' => $score,
                        'confidence_level' => $score >= 90 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
                        'match_reason' => $reason,
                        'record' => $invoice,
                    ];
                }
            }

            // Event bookings paid by bank: the reference someone quotes identifies the booking.
            foreach (EventRegistration::with('event')->where('status', 'attending')->whereIn('payment_status', ['unpaid', 'part_paid'])
                ->where('total', '>', 0)->whereHas('event', fn ($q) => $q->where('club_id', $clubId))->get() as $registration) {
                $balance = $registration->balanceDue();

                if ($balance <= 0 || $amount > $balance + 0.01) {
                    continue;
                }

                $reference = strtolower((string) $registration->payment_reference);
                $surname = strtolower(last(explode(' ', trim($registration->contact_name))));
                $exact = abs($balance - $amount) < 0.01;

                if ($reference !== '' && (str_contains($desc, $reference) || str_contains($ref, $reference))) {
                    $score = $exact ? 100 : 90;
                    $reason = "Booking reference {$registration->payment_reference}".($exact ? ' & amount match' : ' (part payment)');
                } elseif ($exact && $surname !== '' && str_contains($desc, $surname)) {
                    $score = 80;
                    $reason = "Name ({$surname}) & amount match the balance";
                } else {
                    continue;
                }

                $matches[] = [
                    'match_type' => 'event_registration',
                    'target_id' => $registration->id,
                    'target_title' => "Event booking: {$registration->event->title} — {$registration->contact_name} ({$registration->payment_reference})",
                    'target_amount' => $balance,
                    'confidence_score' => $score,
                    'confidence_level' => $score >= 90 ? 'high' : 'medium',
                    'match_reason' => $reason,
                    'record' => $registration,
                ];
            }
        }

        // 2. Outgoing Debits (- amount) -> Match against Unpaid Supplier Bills in Accounts Payable
        if ($amount < 0) {
            $absAmount = abs($amount);
            $unpaidBills = Bill::where('club_id', $clubId)
                ->where('status', 'unpaid')
                ->get();

            foreach ($unpaidBills as $bill) {
                $vendorName = strtolower($bill->vendor_name ?? '');
                $billNo = strtolower($bill->bill_number ?? '');
                $billAmount = (float) $bill->amount;

                $score = 0;
                $reason = '';

                // Rule A: Vendor Name & Amount Match (100%)
                if ($vendorName && str_contains($desc, $vendorName) && abs($billAmount - $absAmount) < 0.01) {
                    $score = 100;
                    $reason = "Vendor Name ({$bill->vendor_name}) & Bill Amount Match";
                }
                // Rule B: Bill Number Reference Match (95%)
                elseif ($billNo && (str_contains($desc, $billNo) || str_contains($ref, $billNo))) {
                    $score = 95;
                    $reason = "Bill Number Ref Match ({$bill->bill_number})";
                }
                // Rule C: Vendor Name Match (75%)
                elseif ($vendorName && str_contains($desc, $vendorName)) {
                    $score = 75;
                    $reason = "Vendor Name Match ({$bill->vendor_name})";
                }
                // Rule D: Bill Amount Match (60%)
                elseif (abs($billAmount - $absAmount) < 0.01) {
                    $score = 60;
                    $reason = 'Vendor Bill Amount Match ('.Currencies::format($billAmount, $club).')';
                }

                if ($score > 0) {
                    $matches[] = [
                        'match_type' => 'supplier_bill',
                        'target_id' => $bill->id,
                        'target_title' => "Vendor: {$bill->vendor_name} (Bill: {$bill->bill_number})",
                        'target_amount' => $billAmount,
                        'confidence_score' => $score,
                        'confidence_level' => $score >= 90 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
                        'match_reason' => $reason,
                        'record' => $bill,
                    ];
                }
            }
        }

        // Sort by confidence_score descending
        usort($matches, fn ($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);

        return $matches;
    }

    /**
     * Execute reconciliation confirmation for a staged transaction.
     */
    public function reconcileTransaction(BankTransaction $transaction, string $matchType, int|string $targetId, array $options = []): bool
    {
        return DB::transaction(function () use ($transaction, $matchType, $targetId, $options) {
            $amount = (float) $transaction->amount;
            $club = Club::find($transaction->club_id);
            $accountingService = app(AccountingService::class);
            $accountingService->seedDefaultAccounts($club);

            $note = 'Reconciled via Bank Statement Match on '.Carbon::now()->format('Y-m-d H:i');
            $targetCode = '4000'; // Default Revenue
            $ledgerAlreadyPosted = false;
            $memo = $transaction->raw_description ?: 'Bank Statement Match';

            if ($matchType === 'member_subscription') {
                $sub = MemberSubscription::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($sub) {
                    $this->billingService->recordPayment(
                        $sub,
                        abs($amount),
                        $transaction->reference ?: 'Bank Import Match',
                        $transaction->raw_description
                    );
                    $note .= " [Member Subscription Invoice: {$sub->invoice_reference}]";
                }
                $targetCode = '4000'; // Membership Dues Income
            } elseif ($matchType === 'event_registration') {
                $registration = EventRegistration::whereHas('event', fn ($q) => $q->where('club_id', $transaction->club_id))->find((int) $targetId);

                if (! $registration) {
                    return false;
                }

                // The payment service records who confirmed it and posts the income (ticket revenue and any fee) itself.
                app(EventPaymentService::class)->markPaid(
                    $registration,
                    auth()->user(),
                    min(abs($amount), $registration->balanceDue()),
                    'Bank transfer',
                    $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : null,
                    'Matched to bank line: '.($transaction->raw_description ?: 'bank statement'),
                    'bank_reconciliation',
                );
                $ledgerAlreadyPosted = true;
                $note .= " [Event booking: {$registration->payment_reference}]";
            } elseif ($matchType === 'supplier_bill') {
                $bill = Bill::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($bill) {
                    if ($bill->status !== 'paid') {
                        $accountingService->markBillAsPaid($bill, auth()->user());
                    }
                    $accountingService->markBillReconciled($bill, $transaction);
                    $ledgerAlreadyPosted = true;
                    $note .= " [Audited Vendor Bill: {$bill->bill_number} - {$bill->vendor_name}]";
                }
                $targetCode = '5000'; // Facility & Clubhouse Maintenance / Expenses
            } elseif ($matchType === 'invoice') {
                $invoice = Invoice::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($invoice) {
                    if ($invoice->status !== 'paid') {
                        $accountingService->markInvoiceAsPaid($invoice, auth()->user());
                    }
                    $accountingService->markInvoiceReconciled($invoice, $transaction);
                    $ledgerAlreadyPosted = true;
                    $note .= " [Invoice: {$invoice->invoice_number} - {$invoice->title}]";
                }
                $targetCode = '4000'; // Membership Dues Income
            } elseif ($matchType === 'charity_relief') {
                $note .= ' [Charity Relief Chest / Provincial Contribution]';
                $targetCode = '4300'; // Raffle & Charity Contributions
            } elseif ($matchType === 'ledger_account') {
                $rawCode = $options['nominal_code'] ?? ($amount > 0 ? '4000' : '5000');
                preg_match('/^(\d+)/', (string) $rawCode, $matches);
                $targetCode = ! empty($matches[1]) ? $matches[1] : ($amount > 0 ? '4000' : '5000');
                $note .= " [Allocated to Ledger Code: {$targetCode}]";
            }

            // Post double-entry journal entry to general ledger if not already posted
            $bankAcc = Account::where('club_id', $club->id)->where('code', '1000')->first();
            $offsetAcc = Account::where('club_id', $club->id)->where('code', $targetCode)->first();
            if (! $offsetAcc) {
                $offsetAcc = Account::create([
                    'club_id' => $club->id,
                    'code' => $targetCode,
                    'name' => 'General Account ('.$targetCode.')',
                    'type' => $amount > 0 ? 'revenue' : 'expense',
                    'currency' => Currencies::codeForClubId($transaction->club_id),
                    'is_active' => true,
                ]);
            }

            $alreadyPosted = JournalEntry::where('club_id', $club->id)
                ->where('source_type', 'bank_transaction')
                ->where('source_id', $transaction->id)
                ->exists();

            if ($bankAcc && $offsetAcc && ! $alreadyPosted && ! $ledgerAlreadyPosted) {
                $absAmount = abs($amount);
                $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date)->format('Y-m-d') : date('Y-m-d');
                if ($amount > 0) {
                    // Money Received: Debit Bank (1000), Credit Revenue/Offset
                    $accountingService->postJournalEntry($club, [
                        'entry_date' => $txDate,
                        'reference_number' => 'RECON-'.$transaction->id,
                        'description' => "Bank Match: {$memo}",
                        'source_type' => 'bank_transaction',
                        'source_id' => $transaction->id,
                        'items' => [
                            ['account_id' => $bankAcc->id, 'debit' => $absAmount, 'credit' => 0, 'memo' => $memo],
                            ['account_id' => $offsetAcc->id, 'debit' => 0, 'credit' => $absAmount, 'memo' => $memo],
                        ],
                    ]);
                } else {
                    // Money Spent: Credit Bank (1000), Debit Expense/Offset
                    $accountingService->postJournalEntry($club, [
                        'entry_date' => $txDate,
                        'reference_number' => 'RECON-'.$transaction->id,
                        'description' => "Bank Match: {$memo}",
                        'source_type' => 'bank_transaction',
                        'source_id' => $transaction->id,
                        'items' => [
                            ['account_id' => $offsetAcc->id, 'debit' => $absAmount, 'credit' => 0, 'memo' => $memo],
                            ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => $absAmount, 'memo' => $memo],
                        ],
                    ]);
                }
            }

            $transaction->update([
                'status' => BankTransactionStatus::Matched,
                'reference' => str_contains($transaction->reference ?? '', '(Matched)') ? $transaction->reference : ($transaction->reference ? $transaction->reference.' (Matched)' : 'Reconciled'),
            ]);

            return true;
        });
    }

    /**
     * Ignore a transaction line.
     */
    public function ignoreTransaction(BankTransaction $transaction): bool
    {
        $transaction->update([
            'status' => BankTransactionStatus::Ignored,
        ]);

        return true;
    }
}
