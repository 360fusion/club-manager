<?php

namespace Launchpad\Billing;

/**
 * When a move from one plan to another takes effect. A step up, or the same plan for a longer period, starts now (Stripe charges the
 * difference, prorated). A step down, or a shorter period, waits for the renewal, so what was paid for is used.
 */
final class PlanChange
{
    public const NOW = 'now';

    public const RENEWAL = 'renewal';

    public static function timing(Plan $from, ?string $fromPeriod, Plan $to, string $toPeriod): string
    {
        if ($to->rank !== $from->rank) {
            return $to->rank > $from->rank ? self::NOW : self::RENEWAL;
        }

        return $fromPeriod === Plan::MONTHLY && $toPeriod === Plan::ANNUAL ? self::NOW : self::RENEWAL;
    }
}
