<?php

namespace Launchpad\Metering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Launchpad\Billing\Entitlements;
use Launchpad\Billing\MeteredBilling;
use Launchpad\Billing\Money;
use Launchpad\Jobs\ReportUsage;
use Launchpad\Models\Workspace;

/**
 * What a team has used of each meter listed in config launchpad.metering.meters, and its quota: the meter's key as a limit of the team's plan
 * (launchpad.billing.plans.*.limits, which staff can change on Plan limits); a plan with no such limit has none.
 * A meter can also be charged for beyond the quota through Stripe (Launchpad\Billing\MeteredBilling).
 * shortcut: a monthly meter resets on the 1st (UTC), not on the team's billing date; use the subscription's period when billing by usage.
 */
final class Usage
{
    private const FOR_EVER = '1970-01-01';

    public function __construct(private readonly Entitlements $entitlements) {}

    /** @return array<string, array{label: string, resets?: string}> */
    public static function meters(): array
    {
        return (array) config('launchpad.metering.meters');
    }

    public function record(Workspace $workspace, string $meter, int $amount = 1): void
    {
        $period = $this->period($meter);
        $billed = $this->billed($workspace, $meter);
        $before = $billed ? $this->used($workspace, $meter) : 0;
        $table = DB::getTablePrefix().'usage_counts';

        // one row a team, meter and period, however many calls arrive together
        DB::table('usage_counts')->upsert(
            [['workspace_id' => $workspace->getKey(), 'meter' => $meter, 'period' => $period, 'used' => $amount]],
            ['workspace_id', 'meter', 'period'],
            ['used' => DB::raw("{$table}.used + ".(int) $amount)],
        );

        // only what goes past the included amount is charged.
        // shortcut: two records at the same moment can both read the same "before"; fine for counts, not for money per unit at scale
        if ($billed !== null) {
            $limit = (int) $this->limit($workspace, $meter);
            $over = max(0, $before + $amount - $limit) - max(0, $before - $limit);
            $over > 0 && ReportUsage::dispatch($workspace->getKey(), $billed['event'], $over, (string) Str::uuid());
        }
    }

    /** @return array{event: string, price: string, unit_amount?: int}|null the meter's Stripe settings when it is billed on the team's plan */
    public function billed(Workspace $workspace, string $meter): ?array
    {
        return config('launchpad.modules.billing') ? (MeteredBilling::meters($this->entitlements->plan($workspace))[$meter] ?? null) : null;
    }

    public function used(Workspace $workspace, string $meter): int
    {
        return (int) DB::table('usage_counts')->where(['workspace_id' => $workspace->getKey(), 'meter' => $meter, 'period' => $this->period($meter)])->value('used');
    }

    /** The quota, or null for none */
    public function limit(Workspace $workspace, string $meter): ?int
    {
        $this->meter($meter);
        $limit = $this->entitlements->limitOr($workspace, $meter, -1);

        return $limit < 0 || $limit >= Entitlements::UNLIMITED ? null : $limit;
    }

    public function within(Workspace $workspace, string $meter, int $amount = 1): bool
    {
        // on a plan that charges for more, the limit is what is included, not a stop
        if ($this->billed($workspace, $meter) !== null) {
            return true;
        }

        $limit = $this->limit($workspace, $meter);

        return $limit === null || $this->used($workspace, $meter) + $amount <= $limit;
    }

    public function monthly(string $meter): bool
    {
        return ($this->meter($meter)['resets'] ?? 'monthly') !== 'never';
    }

    /**
     * Every meter, for the billing page. cost estimates the extra at the meter's unit_amount (Stripe's invoice is the final word).
     *
     * @return list<array{key: string, label: string, used: int, limit: ?int, monthly: bool, billed: bool, extra: int, cost: ?string}>
     */
    public function summary(Workspace $workspace): array
    {
        return collect(self::meters())->map(function (array $meter, string $key) use ($workspace) {
            $used = $this->used($workspace, $key);
            $limit = $this->limit($workspace, $key);
            $billed = $this->billed($workspace, $key);
            $extra = $billed ? max(0, $used - (int) $limit) : 0;

            return [
                'key' => $key, 'label' => __($meter['label'] ?? $key), 'used' => $used, 'limit' => $limit, 'monthly' => $this->monthly($key),
                'billed' => $billed !== null, 'extra' => $extra, 'cost' => $billed && isset($billed['unit_amount']) ? Money::format($extra * (int) $billed['unit_amount']) : null,
            ];
        })->values()->all();
    }

    /** @return array{label: string, resets?: string} */
    private function meter(string $key): array
    {
        return self::meters()[$key] ?? throw new InvalidArgumentException("No meter called {$key}: list it in config launchpad.metering.meters.");
    }

    private function period(string $meter): string
    {
        return $this->monthly($meter) ? now()->utc()->startOfMonth()->toDateString() : self::FOR_EVER;
    }
}
