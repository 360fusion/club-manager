<?php

namespace Launchpad\Billing;

use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;

/**
 * Revenue worked out from the subscriptions table and the plan prices in config, for HQ. Paying means active or past due (Stripe is still
 * trying the card); a trial is not paying yet.
 */
final class Revenue
{
    private const PAYING = ['active', 'past_due'];

    public function __construct(private PlanCatalog $plans) {}

    /**
     * Monthly recurring revenue in the smallest currency unit: each paying subscription's plan price (per seat times seats), an annual price
     * divided by 12. shortcut: list prices, so a promo code's discount and metered usage are not in it; read Stripe's invoices when that matters.
     *
     * @return array{amount: int, unknown: int} unknown: paying subscriptions on a price that is not in config (or with several prices), left out
     */
    public function mrr(): array
    {
        $amount = 0;
        $unknown = 0;

        foreach (Subscription::whereIn('stripe_status', self::PAYING)->with('items')->get() as $subscription) {
            [$plan, $period, $seats] = $this->plans->ofSubscription($subscription) ?? [null, null, 1];
            $price = $plan?->amount($period);

            if ($price === null) {
                $unknown++;

                continue;
            }

            $price *= $plan->perSeat ? $seats : 1;
            $amount += $period === Plan::ANNUAL ? intdiv($price, 12) : $price;
        }

        return ['amount' => $amount, 'unknown' => $unknown];
    }

    /** Subscriptions started in the last $days days that got as far as paying (or are still in a trial) */
    public function started(int $days = 30): int
    {
        return Subscription::where('created_at', '>=', now()->subDays($days))->whereNotIn('stripe_status', ['incomplete', 'incomplete_expired'])->count();
    }

    /**
     * Subscriptions that ended in the last $days days, and as a share of those running when the window opened
     *
     * @return array{count: int, rate: ?float} rate is null when nothing was running at the start
     */
    public function churned(int $days = 30): array
    {
        $start = now()->subDays($days);
        $ended = Subscription::whereBetween('ends_at', [$start, now()])->count();
        $running = $this->runningAt($start);

        return ['count' => $ended, 'rate' => $running > 0 ? round($ended / $running * 100, 1) : null];
    }

    private function runningAt(Carbon $at): int
    {
        return Subscription::where('created_at', '<', $at)->whereNotIn('stripe_status', ['incomplete', 'incomplete_expired'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))->count();
    }
}
