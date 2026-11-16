<?php

namespace Launchpad\Billing;

use Laravel\Cashier\Cashier;

final class Money
{
    /** An amount in the smallest currency unit, written for the pages: 1500 is £15.00 */
    public static function format(int $amount): string
    {
        return Cashier::formatAmount($amount, config('launchpad.billing.currency'), config('launchpad.billing.locale') ?: app()->getLocale());
    }
}
