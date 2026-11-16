<?php

namespace Launchpad\Billing;

/**
 * Charging for use beyond a plan's quota through Stripe. A meter is billed on a plan when the meter has `stripe` settings (the meter event
 * name and the metered price) and the plan lists it under `metered`. Its limit on that plan is then what is included rather than a hard stop,
 * and only what goes past it is reported to Stripe, so the Stripe price is a plain price per unit. A meter on no plan's `metered` list
 * works as before.
 * shortcut: the included amount resets on the 1st (as the meter does), not on the subscription's billing date; a few units at the turn of a
 * period can land in the neighbouring invoice. Keep the metered meters the same on every paid plan: a plan change in Stripe's portal keeps
 * the prices the subscription already has.
 */
final class MeteredBilling
{
    /** @return array<string, array{event: string, price: string, unit_amount?: int}> meter key => its Stripe settings, for the meters billed on $plan */
    public static function meters(Plan $plan): array
    {
        $listed = (array) config("launchpad.billing.plans.{$plan->key}.metered", []);

        return collect((array) config('launchpad.metering.meters'))
            ->filter(fn ($meter, $key) => in_array($key, $listed, true) && filled($meter['stripe']['event'] ?? null) && filled($meter['stripe']['price'] ?? null))
            ->map(fn ($meter) => $meter['stripe'])
            ->all();
    }

    /** @return list<string> the metered prices a subscription to $plan carries beside its plan price */
    public static function prices(Plan $plan): array
    {
        return array_values(array_unique(array_column(self::meters($plan), 'price')));
    }
}
