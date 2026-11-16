<?php

namespace Launchpad\Billing;

use Illuminate\Support\Facades\Log;
use Launchpad\Models\Setting;
use Launchpad\Models\Workspace;

/**
 * What a workspace may do, from the plan it is paying for.
 *
 * The plan comes from the subscription: no valid subscription (none, ended, past due) is the free plan, and so is a subscription to a price
 * this app does not know. That last one is logged, and never the top plan: a wrong or missing price id must not hand out the best features.
 */
class Entitlements
{
    /** A limit that is not one */
    public const UNLIMITED = 999999;

    public function __construct(private readonly PlanCatalog $catalog) {}

    public function plan(Workspace $workspace): Plan
    {
        return $this->resolve($workspace)[0];
    }

    /** @return array{0: Plan, 1: ?string} the plan and the period the workspace pays for it */
    public function resolve(Workspace $workspace): array
    {
        $subscription = $workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'));

        if ($subscription === null || ! $subscription->valid()) {
            return [$this->catalog->free(), null];
        }

        $known = $this->catalog->ofSubscription($subscription);

        if ($known === null) {
            Log::error('Subscription to a price this app does not know', ['workspace' => $workspace->id, 'price' => $subscription->stripe_price]);

            return [$this->catalog->free(), null];
        }

        return [$known[0], $known[1]];
    }

    public function allows(Workspace $workspace, string $feature): bool
    {
        return in_array($feature, $this->plan($workspace)->features, true);
    }

    /** The limit for this workspace: for a per-seat plan, the seats it pays for; else the admin's override, else the plan's */
    public function limit(Workspace $workspace, string $name): int
    {
        $plan = $this->plan($workspace);

        if ($name === 'seats' && $plan->perSeat) {
            return (int) ($workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'))?->quantity ?? 1);
        }

        return $this->planLimit($plan, $name);
    }

    /** The limit for this workspace, or $default when its plan has no limit of that name */
    public function limitOr(Workspace $workspace, string $name, int $default): int
    {
        return array_key_exists($name, $this->plan($workspace)->limits) ? $this->limit($workspace, $name) : $default;
    }

    /** The plan's limit with the admin's override (Setting plan_limit_<plan>_<name>) applied */
    public function planLimit(Plan $plan, string $name): int
    {
        $override = Setting::get("plan_limit_{$plan->key}_{$name}");

        return is_numeric($override) ? (int) $override : (int) ($plan->limits[$name] ?? 0);
    }

    /** Whether one more of something fits: $current is how many there are now */
    public function within(Workspace $workspace, string $name, int $current): bool
    {
        $limit = $this->limit($workspace, $name);

        return $limit >= self::UNLIMITED || $current < $limit;
    }
}
