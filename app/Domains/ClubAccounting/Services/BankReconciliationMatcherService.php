<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankMatchRule;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\DocumentStatus;
use App\Exceptions\LedgerRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Services\AccountingService;
use App\Services\Events\EventPaymentService;
use App\Services\Meetings\MeetingCollections;
use App\Services\Meetings\MeetingReplies;
use App\Support\Currencies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankReconciliationMatcherService
{
    protected SubscriptionBillingService $billingService;

    public function __construct(SubscriptionBillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Only these match types repeat for the same payee: a specific bill, invoice or booking is
     * a one-off document, so remembering it would just suggest something already settled.
     */
    private const REMEMBERED_MATCH_TYPES = ['ledger_account', 'charity_relief'];

    /**
     * Reduce a bank description to the stable part that identifies the payee: lowercase, single
     * spaces, trailing reference/date tokens (anything containing a digit) stripped.
     */
    public function normalizeDescription(string $raw): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($raw)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        while ($tokens !== [] && preg_match('/\d/', end($tokens))) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    /**
     * Remember how a recurring line was matched, so it is suggested next time. A repeat with the
     * same outcome strengthens the rule; a different outcome replaces it (the treasurer changed their mind).
     */
    private function rememberMatch(BankTransaction $transaction, string $matchType, ?string $nominalCode): void
    {
        $pattern = $this->normalizeDescription((string) $transaction->raw_description);

        if (! in_array($matchType, self::REMEMBERED_MATCH_TYPES, true) || strlen($pattern) < 4) {
            return;
        }

        $rule = BankMatchRule::firstOrNew(['club_id' => $transaction->club_id, 'description_pattern' => $pattern, 'match_type' => $matchType]);

        if ($rule->exists && $rule->nominal_code === $nominalCode) {
            $rule->hit_count++;
        } else {
            $rule->hit_count = 1;
            $rule->nominal_code = $nominalCode;
            $rule->created_from_transaction_id = $transaction->id;
        }

        $rule->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rememberedSuggestion(BankTransaction $transaction): ?array
    {
        $pattern = $this->normalizeDescription((string) $transaction->raw_description);

        if (strlen($pattern) < 4) {
            return null;
        }

        $rule = BankMatchRule::where('club_id', $transaction->club_id)->where('description_pattern', $pattern)->orderByDesc('hit_count')->first();

        if (! $rule) {
            return null;
        }

        $account = $rule->nominal_code ? Account::where('club_id', $transaction->club_id)->where('code', $rule->nominal_code)->first() : null;

        return [
            'match_type' => $rule->match_type,
            'target_id' => 0,
            'target_title' => $rule->match_type === 'charity_relief' ? 'Charity Relief Chest / Provincial Contribution' : 'Ledger '.$rule->nominal_code.($account ? " — {$account->name}" : ''),
            'target_amount' => abs((float) $transaction->amount),
            'confidence_score' => 95,
            'confidence_level' => 'high',
            'match_reason' => 'Remembered from a previous match',
            'nominal_code' => $rule->nominal_code,
            'remembered' => true,
            'rule_id' => $rule->id,
            'record' => null,
        ];
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
                        'confidence_level' => $score >= 90 ? 'high' : 'medium',
                        'match_reason' => $reason,
                        'record' => $sub,
                    ];
                }
            }

            // Unpaid member invoices (the Inertia accounting layer's Invoice model — distinct
            // from MemberSubscription above, e.g. one-off charges like locker rental).
            $unpaidInvoices = Invoice::where('club_id', $clubId)
                ->where('status', DocumentStatus::Unpaid->value)
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
                        'confidence_level' => $score >= 90 ? 'high' : 'medium',
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
                } elseif ($exact && $registration->said_paid_at) {
                    $score = 70;
                    $reason = 'The amount matches the balance';
                } else {
                    continue;
                }

                if ($registration->said_paid_at) {
                    $score = min(100, $score + 5);
                    $reason .= '; they said they paid by bank on '.$registration->said_paid_at->format('j M');
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

        if ($amount > 0) {
            array_push($matches, ...$this->diningMatches($clubId, $desc, $ref, $amount));
            array_push($matches, ...$this->collectionMatches($clubId, $desc, $amount));
        }

        // 2. Outgoing Debits (- amount) -> Match against Unpaid Supplier Bills in Accounts Payable
        if ($amount < 0) {
            $absAmount = abs($amount);
            $unpaidBills = Bill::where('club_id', $clubId)
                ->where('status', DocumentStatus::Unpaid->value)
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
                        'confidence_level' => $score >= 90 ? 'high' : 'medium',
                        'match_reason' => $reason,
                        'record' => $bill,
                    ];
                }
            }
        }

        // Sort by confidence_score descending
        usort($matches, fn ($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);

        // A remembered match for this payee goes first; the heuristics above stay as the fallback.
        if ($remembered = $this->rememberedSuggestion($transaction)) {
            array_unshift($matches, $remembered);
        }

        return $matches;
    }

    /**
     * Execute reconciliation confirmation for a staged transaction.
     */
    public function reconcileTransaction(BankTransaction $transaction, string $matchType, int|string $targetId, array $options = []): bool
    {
        return DB::transaction(function () use ($transaction, $matchType, $targetId, $options) {
            // Checked on the locked line: matching it again would record the payment a second time.
            if (BankTransaction::whereKey($transaction->id)->lockForUpdate()->first()?->status === BankTransactionStatus::Matched) {
                throw new LedgerRuleException('This bank line has already been reconciled.');
            }

            $amount = (float) $transaction->amount;
            $club = Club::find($transaction->club_id);
            $accountingService = app(AccountingService::class);
            $accountingService->seedDefaultAccounts($club);

            $note = 'Reconciled via Bank Statement Match on '.Carbon::now()->format('Y-m-d H:i');
            $targetCode = '4000'; // Default Revenue
            $ledgerAlreadyPosted = false;
            $memo = $transaction->raw_description ?: 'Bank Statement Match';
            // The money moves in the ledger account of the bank account the line belongs to, on the day the bank shows.
            $bankAcc = ($transaction->bankAccount?->account_id ? Account::where('club_id', $club->id)->find($transaction->bankAccount->account_id) : null)
                ?? $accountingService->defaultBankAccount($club);
            $lineDate = Carbon::parse($transaction->transaction_date);

            if ($matchType === 'member_subscription') {
                $sub = MemberSubscription::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($sub) {
                    $this->billingService->recordPayment(
                        $sub,
                        abs($amount),
                        $transaction->reference ?: 'Bank Import Match',
                        $transaction->raw_description,
                        $bankAcc->id,
                        $lineDate,
                    );
                    $ledgerAlreadyPosted = true;
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
                    $lineDate,
                    'Matched to bank line: '.($transaction->raw_description ?: 'bank statement'),
                    'bank_reconciliation',
                    bankAccount: $bankAcc,
                );
                $registration->forceFill(['bank_transaction_id' => $transaction->id])->save();
                $ledgerAlreadyPosted = true;
                $note .= " [Event booking: {$registration->payment_reference}]";
            } elseif ($matchType === 'meeting_dining') {
                $rsvp = MeetingRsvp::whereHas('meeting', fn ($q) => $q->where('club_id', $transaction->club_id))->with(['meeting', 'guests'])->find((int) $targetId);

                if (! $rsvp) {
                    return false;
                }

                $this->payDining($rsvp, abs($amount), $lineDate, $transaction);
                $note .= ' [Meeting dining: '.$rsvp->meeting->title.']';
                $targetCode = '4200'; // Bar & Dining Sales
            } elseif ($matchType === 'meeting_collections') {
                // The alms and raffle cash entered at meetings (MeetingCollections): money awaiting banking moves to the bank.
                $collections = app(MeetingCollections::class);
                $meetingIds = (int) $targetId === 0
                    ? $collections->unbanked($transaction->club_id)->map(fn (array $group) => $group['meeting']->id)->all()
                    : [(int) $targetId];
                $collections->markBanked($transaction->club_id, $meetingIds, $transaction->id, abs($amount));
                MeetingCollections::awaitingBanking($club);
                $targetCode = MeetingCollections::AWAITING_BANKING;
                $note .= ' [Alms and raffle paid in]';
            } elseif ($matchType === 'supplier_bill') {
                $bill = Bill::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($bill) {
                    if ($bill->status !== DocumentStatus::Paid->value) {
                        $accountingService->markBillAsPaid($bill, auth()->user(), $bankAcc, $lineDate);
                    }
                    $accountingService->markBillReconciled($bill, $transaction);
                    $ledgerAlreadyPosted = true;
                    $note .= " [Audited Vendor Bill: {$bill->bill_number} - {$bill->vendor_name}]";
                }
                $targetCode = '5000'; // Facility & Clubhouse Maintenance / Expenses
            } elseif ($matchType === 'invoice') {
                $invoice = Invoice::where('club_id', $transaction->club_id)->find((int) $targetId);
                if ($invoice) {
                    if ($invoice->status !== DocumentStatus::Paid->value) {
                        $accountingService->markInvoiceAsPaid($invoice, auth()->user(), $bankAcc, $lineDate);
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

            if (! $alreadyPosted && ! $ledgerAlreadyPosted) {
                $absAmount = abs($amount);
                $txDate = Carbon::parse($transaction->transaction_date)->format('Y-m-d');
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

            $this->rememberMatch($transaction, $matchType, $matchType === 'charity_relief' ? '4300' : ($matchType === 'ledger_account' ? $targetCode : null));

            $transaction->update([
                'status' => BankTransactionStatus::Matched,
                'reference' => str_contains($transaction->reference ?? '', '(Matched)') ? $transaction->reference : ($transaction->reference ? $transaction->reference.' (Matched)' : 'Reconciled'),
            ]);

            return true;
        });
    }

    /**
     * The dining places a reply still owes for, in the order a payment covers them: the member, then each guest.
     *
     * @return list<array{guest: ?int, fee: float}>
     */
    private function diningPlaces(MeetingRsvp $rsvp): array
    {
        $owed = MeetingReplies::owed($rsvp->meeting, $rsvp);
        $places = $owed['member'] ? [['guest' => null, 'fee' => (float) $rsvp->meeting->dining_cost_member]] : [];

        foreach ($rsvp->guests->whereIn('id', $owed['guest_ids']) as $guest) {
            $places[] = ['guest' => $guest->id, 'fee' => MeetingReplies::guestFee($rsvp->meeting, $guest)];
        }

        return $places;
    }

    /**
     * How many of a reply's places an amount pays for exactly (0 when it does not add up to whole places).
     *
     * @param  list<array{guest: ?int, fee: float}>  $places
     */
    private function placesCovered(array $places, float $amount): int
    {
        $sum = 0.0;

        foreach ($places as $index => $place) {
            $sum += $place['fee'];
            if (abs($sum - $amount) < 0.01) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * Meeting dining paid by bank: the reference on the summons (or the surname) and an amount that pays whole places.
     * A member who said they had paid ranks higher.
     *
     * @return list<array<string, mixed>>
     */
    private function diningMatches(int $clubId, string $desc, string $ref, float $amount): array
    {
        $matches = [];
        $rsvps = MeetingRsvp::whereIn('attendance_status', MeetingReplies::ATTENDING)
            ->whereHas('meeting', fn ($q) => $q->where('club_id', $clubId)->where('meeting_date', '>=', now()->subMonths(6)->toDateString()))
            ->with(['meeting', 'guests', 'user'])
            ->get();

        foreach ($rsvps as $rsvp) {
            $places = $this->diningPlaces($rsvp);
            $covered = $this->placesCovered($places, $amount);

            if ($covered === 0 || ! $rsvp->user) {
                continue;
            }

            $all = $covered === count($places);
            $reference = strtolower($rsvp->payment_reference ?: MeetingReplies::paymentReference($rsvp->meeting, $rsvp->user));
            $surname = strtolower(last(explode(' ', trim($rsvp->user->name))));

            if (str_contains($desc, $reference) || str_contains($ref, $reference)) {
                $score = $all ? 100 : 90;
                $reason = 'Dining reference '.strtoupper($reference).($all ? ' & amount match' : " (pays {$covered} of ".count($places).' places)');
            } elseif ($all && $surname !== '' && str_contains($desc, $surname)) {
                $score = 80;
                $reason = "Name ({$surname}) & amount match the dining owed";
            } elseif ($all && $rsvp->said_paid_at) {
                $score = 70;
                $reason = 'The amount matches the dining owed';
            } else {
                continue;
            }

            if ($rsvp->said_paid_at) {
                $score = min(100, $score + 5);
                $reason .= '; they said they paid by bank on '.$rsvp->said_paid_at->format('j M');
            }

            $matches[] = [
                'match_type' => 'meeting_dining',
                'target_id' => $rsvp->id,
                'target_title' => 'Dining: '.$rsvp->meeting->title.' ('.$rsvp->meeting->meeting_date->format('j M').') — '.$rsvp->user->name,
                'target_amount' => array_sum(array_column($places, 'fee')),
                'confidence_score' => $score,
                'confidence_level' => $score >= 90 ? 'high' : 'medium',
                'match_reason' => $reason,
                'record' => $rsvp,
            ];
        }

        return $matches;
    }

    /**
     * A paying-in line for the alms and raffle cash entered at meetings: one meeting's amount, or everything still
     * waiting when several meetings were paid in together. Words such as "alms", "cash" or "paid in" rank it higher.
     *
     * @return list<array<string, mixed>>
     */
    private function collectionMatches(int $clubId, string $desc, float $amount): array
    {
        $groups = app(MeetingCollections::class)->unbanked($clubId);
        $bonus = preg_match('/alms|raffle|collect|charity|cash|cheque|paid in|deposit|counter|lodgement/i', $desc) ? 10 : 0;
        $matches = [];

        foreach ($groups as $group) {
            if (abs($group['amount'] - $amount) < 0.01) {
                $matches[] = [
                    'match_type' => 'meeting_collections',
                    'target_id' => $group['meeting']->id,
                    'target_title' => 'Alms and raffle: '.$group['meeting']->title.' ('.$group['meeting']->meeting_date->format('j M').')',
                    'target_amount' => $group['amount'],
                    'confidence_score' => 80 + $bonus,
                    'confidence_level' => 80 + $bonus >= 90 ? 'high' : 'medium',
                    'match_reason' => 'The cash and cheques from that meeting'.($bonus ? ', and the line looks like a paying-in' : ''),
                    'record' => null,
                ];
            }
        }

        if ($groups->count() > 1 && abs($groups->sum('amount') - $amount) < 0.01) {
            $matches[] = [
                'match_type' => 'meeting_collections',
                'target_id' => 0,
                'target_title' => 'Alms and raffle from '.$groups->count().' meetings',
                'target_amount' => round($groups->sum('amount'), 2),
                'confidence_score' => 75 + $bonus,
                'confidence_level' => 'medium',
                'match_reason' => 'All the alms and raffle cash still waiting to be paid in',
                'record' => null,
            ];
        }

        return $matches;
    }

    /**
     * Mark the dining places a bank line pays for as paid by bank, member first, linked to the line so the Secretary
     * sees it was seen in the bank.
     */
    private function payDining(MeetingRsvp $rsvp, float $amount, Carbon $lineDate, BankTransaction $transaction): void
    {
        $places = $this->diningPlaces($rsvp);
        $covered = $this->placesCovered($places, $amount);

        if ($covered === 0) {
            throw new LedgerRuleException('This amount does not match the dining places still to pay on that reply.');
        }

        $paid = ['payment_status' => 'paid', 'payment_method' => 'bank', 'paid_at' => $lineDate, 'paid_recorded_by' => auth()->id(), 'bank_transaction_id' => $transaction->id];

        foreach (array_slice($places, 0, $covered) as $place) {
            $place['guest'] === null
                ? $rsvp->forceFill($paid)->save()
                : MeetingRsvpGuest::whereKey($place['guest'])->update($paid);
        }
    }

    /** One-off documents that a reference and amount identify for certain, so a sure match can be confirmed in bulk. */
    public const EXACT_MATCH_TYPES = ['event_registration', 'meeting_dining', 'member_subscription', 'invoice', 'supplier_bill'];

    /**
     * The one suggestion for a line that is certain (100: reference and amount), or null when there is none or more
     * than one, so a bulk confirm never has to choose.
     *
     * @return array<string, mixed>|null
     */
    public function exactMatch(BankTransaction $transaction): ?array
    {
        $sure = array_values(array_filter($this->suggestMatches($transaction), fn (array $match) => $match['confidence_score'] >= 100 && in_array($match['match_type'], self::EXACT_MATCH_TYPES, true)));

        return count($sure) === 1 ? $sure[0] : null;
    }

    /**
     * Ignore a transaction line.
     */
    public function ignoreTransaction(BankTransaction $transaction): bool
    {
        // A reconciled line has its entry in the books; ignoring it would hide that entry without reversing it.
        if ($transaction->status === BankTransactionStatus::Matched) {
            throw new LedgerRuleException('This bank line has already been reconciled, so it cannot be ignored.');
        }

        $transaction->update([
            'status' => BankTransactionStatus::Ignored,
        ]);

        return true;
    }
}
