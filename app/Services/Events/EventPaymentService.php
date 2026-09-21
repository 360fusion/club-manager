<?php

namespace App\Services\Events;

use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\EventPaymentLog;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\StripeGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to whether a booking has been paid goes through here. Each one adds a line to the
 * booking's payment history (who, when, how much, method, why) and keeps the books in step.
 */
class EventPaymentService
{
    public function __construct(private AccountingService $accounting) {}

    /**
     * Record money received. Defaults to the whole balance; a smaller amount records a part payment.
     */
    public function markPaid(EventRegistration $registration, ?User $actor, ?float $amount = null, ?string $method = null, ?Carbon $receivedAt = null, ?string $comment = null, string $source = 'admin', ?string $externalId = null, ?string $paymentIntent = null): EventRegistration
    {
        $received = 0.0;

        $registration = DB::transaction(function () use ($registration, $actor, &$amount, $method, $receivedAt, $comment, $source, $externalId, $paymentIntent, &$received) {
            $registration = $this->lock($registration);
            $balance = $registration->balanceDue();

            if ((float) $registration->total <= 0 || $balance <= 0 || in_array($registration->payment_status, ['waived', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment' => 'There is nothing left to pay on this booking.']);
            }

            $amount = round($amount ?? $balance, 2);

            if ($amount <= 0 || $amount > $balance + 0.001) {
                throw ValidationException::withMessages(['amount' => 'The amount must be more than nothing and no more than the '.$registration->event->club->currencySymbol().number_format($balance, 2).' still owed.']);
            }

            $method ??= $registration->paymentMethod?->label;
            $registration->amount_paid = round((float) $registration->amount_paid + $amount, 2);
            $registration->payment_status = $registration->statusFromAmounts();
            $registration->paid_at = $registration->payment_status === 'paid' ? ($receivedAt ?? now()) : $registration->paid_at;
            $registration->stripe_payment_intent = $paymentIntent ?? $registration->stripe_payment_intent;
            $registration->save();

            $this->log($registration, match ($source) {
                'stripe_webhook' => 'stripe_paid',
                'paypal' => 'paypal_paid',
                default => null,
            } ?? ($registration->payment_status === 'paid' ? 'marked_paid' : 'marked_part_paid'), $amount, $method, $comment, $actor, $source, $receivedAt, $externalId);
            $this->postIncome($registration, $amount, $actor);
            $received = $amount;

            return $registration;
        });

        app(EventMailer::class)->paymentReceived($registration, $received);

        return $registration;
    }

    /**
     * Take back a payment recorded by mistake. A reason is required so the history explains it.
     */
    public function markUnpaid(EventRegistration $registration, ?User $actor, string $comment): EventRegistration
    {
        return DB::transaction(function () use ($registration, $actor, $comment) {
            $registration = $this->lock($registration);
            $this->requireReason($comment);

            $paid = (float) $registration->amount_paid - (float) $registration->amount_refunded;

            if ($paid <= 0 && $registration->payment_status !== 'waived') {
                throw ValidationException::withMessages(['payment' => 'This booking has no payment to take back.']);
            }

            $registration->amount_paid = 0;
            $registration->amount_refunded = 0;
            $registration->payment_status = 'unpaid';
            $registration->paid_at = null;
            $registration->save();

            $this->log($registration, 'marked_unpaid', max(0, $paid), null, $comment, $actor, 'admin');

            if ($paid > 0) {
                $this->postReversal($registration, $paid, $actor, 'Payment taken back');
            }

            return $registration;
        });
    }

    /**
     * Let someone off paying. Only for a booking nothing has been paid on.
     */
    public function waive(EventRegistration $registration, ?User $actor, string $comment): EventRegistration
    {
        return DB::transaction(function () use ($registration, $actor, $comment) {
            $registration = $this->lock($registration);
            $this->requireReason($comment);

            if ((float) $registration->amount_paid > 0) {
                throw ValidationException::withMessages(['payment' => 'Take the payment back or refund it before waiving the charge.']);
            }

            $registration->update(['payment_status' => 'waived']);
            $this->log($registration, 'waived', (float) $registration->total, null, $comment, $actor, 'admin');

            return $registration;
        });
    }

    /**
     * Record money given back (defaults to everything paid). Card refunds through Stripe come with online payments.
     */
    public function refund(EventRegistration $registration, ?User $actor, string $comment, ?float $amount = null): EventRegistration
    {
        $refunded = 0.0;

        $registration = DB::transaction(function () use ($registration, $actor, $comment, $amount, &$refunded) {
            $registration = $this->lock($registration);
            $this->requireReason($comment);

            $refundable = round((float) $registration->amount_paid - (float) $registration->amount_refunded, 2);
            $amount = round($amount ?? $refundable, 2);

            if ($refundable <= 0 || $amount <= 0 || $amount > $refundable + 0.001) {
                throw ValidationException::withMessages(['amount' => 'There is nothing to refund, or the amount is more than was paid.']);
            }

            // A card payment is refunded through Stripe first; if Stripe refuses, nothing is recorded.
            $method = $registration->paymentMethod;
            $viaStripe = $method?->type === ClubPaymentMethod::CARD && $registration->stripe_payment_intent && $method->hasStripeKeys();

            if ($viaStripe) {
                app(StripeGateway::class)->refund($method, $registration->stripe_payment_intent, (int) round($amount * 100));
            }

            $viaPayPal = ! $viaStripe && $registration->paypal_capture_id && $method?->type === ClubPaymentMethod::PAYPAL && $method->isReadyForPayPal();

            if ($viaPayPal) {
                app(PayPalGateway::class)->refund($method, $registration->paypal_capture_id, $amount, $registration->event->club->currencyCode());
            }

            $registration->amount_refunded = round((float) $registration->amount_refunded + $amount, 2);

            if ($registration->amount_refunded + 0.001 >= (float) $registration->amount_paid) {
                $registration->payment_status = 'refunded';
            }

            $registration->save();

            $this->log($registration, 'refunded', $amount, $viaStripe ? 'Stripe refund' : ($viaPayPal ? 'PayPal refund' : null), $comment, $actor, 'admin');
            $this->postReversal($registration, $amount, $actor, 'Refund');
            $refunded = $amount;

            return $registration;
        });

        app(EventMailer::class)->refunded($registration, $refunded);

        return $registration;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(EventRegistration $registration): array
    {
        return $registration->paymentLog()->with('user:id,name')->get()->map(fn (EventPaymentLog $row) => [
            'id' => $row->id,
            'action' => $row->action,
            'amount' => $row->amount,
            'method' => $row->method,
            'comment' => $row->comment,
            'by' => $row->user?->name ?? match ($row->source) {
                'stripe_webhook' => 'Stripe',
                'paypal' => 'PayPal',
                'bank_reconciliation' => 'Bank reconciliation',
                default => 'System',
            },
            'source' => $row->source,
            'received_at' => $row->received_at?->format('j M Y'),
            'at' => $row->created_at?->format('j M Y H:i'),
        ])->all();
    }

    private function lock(EventRegistration $registration): EventRegistration
    {
        return EventRegistration::with(['event.club', 'paymentMethod'])->whereKey($registration->id)->lockForUpdate()->firstOrFail();
    }

    private function requireReason(string $comment): void
    {
        if (mb_strlen(trim($comment)) < 3) {
            throw ValidationException::withMessages(['comment' => 'Please give a short reason, so the history explains what happened.']);
        }
    }

    private function log(EventRegistration $registration, string $action, float $amount, ?string $method, ?string $comment, ?User $actor, string $source, ?Carbon $receivedAt = null, ?string $externalId = null): void
    {
        EventPaymentLog::create([
            'registration_id' => $registration->id,
            'action' => $action,
            'amount' => $amount,
            'method' => $method,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            'user_id' => $actor?->id,
            'source' => $source,
            'received_at' => $receivedAt ?? now(),
            'external_id' => $externalId,
        ]);
    }

    /**
     * Money in: debit the bank (or cash for the door), credit ticket income and any booking fee.
     */
    private function postIncome(EventRegistration $registration, float $amount, ?User $actor): void
    {
        [$club, $bank, $income, $fees, $share] = $this->accountsFor($registration, $amount);

        $items = [
            ['account_id' => $bank->id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Event payment'],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => round($amount - $share, 2), 'memo' => 'Event ticket revenue'],
        ];

        if ($share > 0) {
            $items[] = ['account_id' => $fees->id, 'debit' => 0, 'credit' => $share, 'memo' => 'Booking fees'];
        }

        $this->accounting->postJournalEntry($club, [
            'description' => 'Event payment: '.$registration->event->title.' - '.$registration->contact_name,
            'source_type' => 'EventRegistration',
            'source_id' => $registration->id,
            'created_by' => $actor?->id,
            'items' => $items,
        ]);
    }

    private function postReversal(EventRegistration $registration, float $amount, ?User $actor, string $why): void
    {
        [$club, $bank, $income, $fees, $share] = $this->accountsFor($registration, $amount);

        $items = [
            ['account_id' => $income->id, 'debit' => round($amount - $share, 2), 'credit' => 0, 'memo' => 'Event ticket revenue reversed'],
            ['account_id' => $bank->id, 'debit' => 0, 'credit' => $amount, 'memo' => $why],
        ];

        if ($share > 0) {
            $items[] = ['account_id' => $fees->id, 'debit' => $share, 'credit' => 0, 'memo' => 'Booking fees reversed'];
        }

        $this->accounting->postJournalEntry($club, [
            'description' => $why.': '.$registration->event->title.' - '.$registration->contact_name,
            'source_type' => 'EventRegistrationReversal',
            'source_id' => $registration->id,
            'created_by' => $actor?->id,
            'items' => $items,
        ]);
    }

    /**
     * @return array{0: Club, 1: Account, 2: Account, 3: Account, 4: float}
     */
    private function accountsFor(EventRegistration $registration, float $amount): array
    {
        $club = $registration->event->club;
        $this->accounting->seedDefaultAccounts($club);

        $fees = Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '4110'],
            ['name' => 'Event Booking Fees', 'type' => 'revenue', 'currency' => $club->currencyCode(), 'is_active' => true],
        );

        $isCash = $registration->paymentMethod?->type === ClubPaymentMethod::DOOR;
        $bank = Account::where('club_id', $club->id)->where('code', $isCash ? '1100' : '1000')->firstOrFail();
        $income = Account::where('club_id', $club->id)->where('code', '4100')->firstOrFail();

        // Split the money in proportion to how the total was made up.
        $share = (float) $registration->total > 0 ? round($amount * (float) $registration->booking_fee / (float) $registration->total, 2) : 0.0;

        return [$club, $bank, $income, $fees, $share];
    }
}
