<?php

namespace App\Services\Payment;

use App\Models\ClubPlatformAccount;

/**
 * The platform's commission on a card payment, in whole pence: a percentage plus a fixed amount, kept between
 * a minimum and a cap, and never more than the payment itself. A lodge can have its own rate.
 */
class PlatformFees
{
    public function commissionMinor(int $grossMinor, ?ClubPlatformAccount $account = null): int
    {
        if ($grossMinor <= 0) {
            return 0;
        }

        $percent = $account?->commission_percent !== null ? (float) $account->commission_percent : (float) config('platform_payments.commission_percent');
        $fixed = $account?->commission_fixed !== null ? (float) $account->commission_fixed : (float) config('platform_payments.commission_fixed');

        $fee = (int) round($grossMinor * $percent / 100) + (int) round($fixed * 100);
        $fee = max($fee, (int) round((float) config('platform_payments.commission_min') * 100));

        $cap = (int) round((float) config('platform_payments.commission_max') * 100);

        if ($cap > 0) {
            $fee = min($fee, $cap);
        }

        return max(0, min($fee, $grossMinor));
    }

    /**
     * A plain-words description of the rate, for the lodge to read before it agrees.
     */
    public function describe(?ClubPlatformAccount $account, string $symbol): string
    {
        $percent = $account?->commission_percent !== null ? (float) $account->commission_percent : (float) config('platform_payments.commission_percent');
        $fixed = $account?->commission_fixed !== null ? (float) $account->commission_fixed : (float) config('platform_payments.commission_fixed');

        return rtrim(rtrim(number_format($percent, 2), '0'), '.').'%'.($fixed > 0 ? ' + '.$symbol.number_format($fixed, 2) : '').' of each card payment';
    }
}
