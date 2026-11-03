<?php

namespace App\Support;

use App\Models\Club;

/** Which plan a club is on, found from the Stripe price it is subscribed to (config/plans.php) */
final class PlanCatalog
{
    /** @return array{key: string, name: string, prices: array<string, ?string>, limits: array<string, ?int>, modules: list<string>, period: ?string} */
    public static function forClub(Club $club): array
    {
        $subscription = $club->subscription('default');
        $price = $subscription?->valid() ? $subscription->stripe_price : null;

        foreach ((array) config('plans.plans') as $key => $plan) {
            foreach ($plan['prices'] as $period => $id) {
                if (filled($id) && $id === $price) {
                    return ['key' => $key, 'period' => $period] + $plan;
                }
            }
        }

        // A subscription to a price we do not know is shown as the free plan rather than a guess; billing:doctor flags the gap.
        $free = config('plans.free');

        return ['key' => $free, 'period' => null] + config("plans.plans.{$free}");
    }
}
