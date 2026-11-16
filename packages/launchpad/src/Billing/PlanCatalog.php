<?php

namespace Launchpad\Billing;

use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Launchpad\Models\Setting;

/**
 * The plans an app sells, from config('launchpad.billing.plans'), in the order they are listed (the order is their rank: a later plan is a
 * step up). The admin can hold a plan back as "Coming soon" (Setting plan_hold_<key>): it stays on the pricing page but cannot be bought.
 */
class PlanCatalog
{
    /** @var array<string, Plan>|null */
    private ?array $plans = null;

    /** @return array<string, Plan> */
    public function all(): array
    {
        if ($this->plans === null) {
            $this->plans = [];
            $rank = 0;

            foreach ((array) config('launchpad.billing.plans', []) as $key => $plan) {
                $this->plans[$key] = new Plan(
                    key: $key,
                    name: $plan['name'] ?? ucfirst($key),
                    description: $plan['description'] ?? '',
                    rank: $rank++,
                    prices: $plan['prices'] ?? [],
                    // what staff chose on HQ's Plan limits page wins over config
                    features: array_values((array) (Setting::get("plan_features_{$key}") ?? $plan['features'] ?? [])),
                    limits: $plan['limits'] ?? [],
                    perSeat: (bool) ($plan['per_seat'] ?? false),
                    minSeats: max(1, (int) ($plan['min_seats'] ?? 1)),
                    trialDays: max(0, (int) ($plan['trial_days'] ?? 0)),
                );
            }
        }

        return $this->plans;
    }

    public function find(?string $key): ?Plan
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /** What a workspace with no (valid) subscription has */
    public function free(): Plan
    {
        return $this->find(config('launchpad.billing.free_plan', 'free')) ?? throw new \LogicException('The free plan is not in config launchpad.billing.plans.');
    }

    /**
     * The plan a subscription is to, its period, the seats, and the item for the plan's price: a subscription with metered prices beside the
     * plan's has several items and no single price of its own
     *
     * @return array{0: Plan, 1: string, 2: int, 3: ?SubscriptionItem}|null
     */
    public function ofSubscription(Subscription $subscription): ?array
    {
        if ($known = $this->byPrice($subscription->stripe_price)) {
            // a single price means a single item, so the items are not read
            return [...$known, max(1, (int) $subscription->quantity), null];
        }

        foreach ($subscription->items as $item) {
            if ($known = $this->byPrice($item->stripe_price)) {
                return [...$known, max(1, (int) $item->quantity), $item];
            }
        }

        return null;
    }

    /** @return list<string> every feature there is to give a plan: those the plans list in config, and those the app checks for */
    public function knownFeatures(): array
    {
        $listed = collect((array) config('launchpad.billing.plans'))->flatMap(fn ($plan) => (array) ($plan['features'] ?? []));
        $checked = collect([config('launchpad.mcp.feature'), config('launchpad.sso.feature'), ...array_values((array) config('launchpad.mcp.tool_features', []))]);

        return $listed->merge($checked)->filter(fn ($f) => is_string($f) && $f !== '')->unique()->sort()->values()->all();
    }

    /** The plan and period a Stripe price id belongs to, or null for a price this app does not know */
    public function byPrice(?string $priceId): ?array
    {
        if (blank($priceId)) {
            return null;
        }

        foreach ($this->all() as $plan) {
            foreach ([Plan::MONTHLY, Plan::ANNUAL] as $period) {
                if ($plan->priceId($period) === $priceId) {
                    return [$plan, $period];
                }
            }
        }

        return null;
    }

    public function held(Plan $plan): bool
    {
        return (bool) Setting::get('plan_hold_'.$plan->key, false);
    }

    /** Whether it can be bought now: it has a price for the period and the admin has not held it back */
    public function onSale(Plan $plan, ?string $period = null): bool
    {
        if ($plan->isFree() || $this->held($plan)) {
            return false;
        }

        return $period === null ? $plan->periods() !== [] : $plan->priceId($period) !== null;
    }

    /** The period asked for if the plan has it, else the first one it has */
    public function period(Plan $plan, ?string $asked): ?string
    {
        return in_array($asked, $plan->periods(), true) ? $asked : ($plan->periods()[0] ?? null);
    }

    public function forget(): void
    {
        $this->plans = null;
    }
}
