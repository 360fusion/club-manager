<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionPayment;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Support\Currencies;

/**
 * How a member's dues stand: what is owed, what has been paid and when, what is left, and whether they are on course
 * to have paid in full by the due date. Dues are paid by bank transfer, either in one go when due or by a standing
 * order each month beforehand; the payments come from the bank statement (SubscriptionPayment, written by
 * SubscriptionBillingService::recordPayment).
 */
class DuesTracker
{
    /**
     * One subscription as the member and the Treasurer see it. Expects `payments` loaded.
     *
     * @return array<string, mixed>
     */
    public function describe(MemberSubscription $subscription, Club $club): array
    {
        $money = fn (float $amount) => Currencies::format($amount, $club);
        $due = (float) $subscription->amount_due;
        $paid = (float) $subscription->amount_paid;
        $left = $subscription->status === SubscriptionStatus::Waived ? 0.0 : max(0.0, round($due - $paid, 2));

        return [
            'id' => $subscription->id,
            'year' => $subscription->billing_year,
            'due_date' => $subscription->due_date->format('j M Y'),
            'amount_due' => $money($due),
            'paid' => $money($paid),
            'left' => $money($left),
            'percent' => $due > 0 ? min(100, (int) floor($paid / $due * 100)) : 100,
            'reference' => $subscription->invoice_reference,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'said_paid_on' => $subscription->said_paid_at?->format('j M Y'),
            'pace' => $this->pace($subscription, $left, $money),
            'payments' => $subscription->payments->map(fn (SubscriptionPayment $payment) => [
                'date' => $payment->paid_on->format('j M Y'),
                'amount' => $money((float) $payment->amount),
            ])->values()->all(),
        ];
    }

    /**
     * Where the member stands: paid, on course, behind, overdue, or simply due on the date.
     *
     * @return array{state: string, message: string}
     */
    public function pace(MemberSubscription $subscription, float $left, callable $money): array
    {
        if ($subscription->status === SubscriptionStatus::Waived) {
            return ['state' => 'paid', 'message' => 'Nothing to pay this year.'];
        }

        if ($left <= 0.009) {
            return ['state' => 'paid', 'message' => 'Paid in full. Thank you.'];
        }

        $dueDate = $subscription->due_date;
        $payments = $subscription->payments;

        if ($dueDate->lt(now()->startOfDay())) {
            return ['state' => 'overdue', 'message' => $money($left).' was due on '.$dueDate->format('j M Y').'.'];
        }

        // Two or more payments is someone paying as they go: at their usual payment, will they finish by the due date?
        if ($payments->count() >= 2) {
            $usual = (float) $payments->last()->amount;
            $monthsLeft = max(0, (int) floor(now()->startOfDay()->diffInMonths($dueDate)));
            $byDueDate = round($usual * $monthsLeft, 2);

            if ($byDueDate + 0.009 >= $left) {
                return ['state' => 'on_track', 'message' => 'At '.$money($usual).' a month you\'ll have paid in full by '.$dueDate->format('j M Y').'.'];
            }

            return ['state' => 'behind', 'message' => 'At '.$money($usual).' a month you\'d still owe '.$money($left - $byDueDate).' on '.$dueDate->format('j M Y').'.'];
        }

        return ['state' => 'due', 'message' => $money($left).' due by '.$dueDate->format('j M Y').'.'];
    }

    /**
     * Whether to remind the member today (app:send-dues-reminders): they are behind, overdue, or paying in one go with
     * the due date two weeks off; someone on track or paid is left alone. They are not chased within four weeks of the
     * last reminder, nor for four weeks after saying they have paid, while the Treasurer waits for the statement.
     */
    public function shouldRemind(MemberSubscription $subscription, string $paceState): bool
    {
        $wanted = in_array($paceState, ['behind', 'overdue'], true)
            || ($paceState === 'due' && $subscription->due_date->lte(now()->addDays(14)));

        return $wanted
            && ($subscription->reminder_sent_at === null || $subscription->reminder_sent_at->lte(now()->subDays(28)))
            && ($subscription->said_paid_at === null || $subscription->said_paid_at->lte(now()->subDays(28)));
    }

    /**
     * Where to send it: the lodge's bank details from its bank transfer payment option, if it has one set up.
     *
     * @return array<string, mixed>|null
     */
    public function bankDetails(Club $club): ?array
    {
        $method = ClubPaymentMethod::where('club_id', $club->id)->where('type', ClubPaymentMethod::BANK)->where('is_active', true)->first();

        return $method?->hasBankDetails() ? $method->bankDetails() : null;
    }

    /**
     * Everyone's dues for the lodge's latest billing year, behind and overdue first: the Treasurer's list.
     *
     * @return array{year: ?int, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function forLodge(Club $club): array
    {
        $year = MemberSubscription::where('club_id', $club->id)->max('billing_year');
        $subscriptions = $year === null ? collect() : MemberSubscription::where('club_id', $club->id)->where('billing_year', $year)
            ->with(['member', 'payments'])->get()->filter(fn (MemberSubscription $sub) => $sub->member !== null);

        $order = ['overdue' => 0, 'behind' => 1, 'due' => 2, 'on_track' => 3, 'paid' => 4];
        $rows = $subscriptions
            ->map(fn (MemberSubscription $sub) => ['name' => $sub->member->formatted_rank_name, 'sort' => $sub->member->last_name] + $this->describe($sub, $club))
            ->sortBy(fn (array $row) => [$order[$row['pace']['state']] ?? 9, $row['sort']])
            ->map(fn (array $row) => collect($row)->except('sort')->all())
            ->values()
            ->all();

        return [
            'year' => $year,
            'rows' => $rows,
            'totals' => [
                'due' => Currencies::format((float) $subscriptions->sum('amount_due'), $club),
                'paid' => Currencies::format((float) $subscriptions->sum('amount_paid'), $club),
                'left' => Currencies::format((float) $subscriptions->sum(fn (MemberSubscription $sub) => $sub->status === SubscriptionStatus::Waived ? 0 : $sub->balance_due), $club),
            ],
        ];
    }
}
