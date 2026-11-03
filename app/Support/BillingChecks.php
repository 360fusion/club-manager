<?php

namespace App\Support;

/** The checks behind `billing:doctor`: is the app set up to take a club's subscription. Live-site settings are in HealthChecks (`app:doctor`). */
final class BillingChecks
{
    /** @return list<array{name: string, ok: bool, detail: string}> */
    public static function run(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail) use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
        };

        $add('Stripe keys', filled(config('cashier.key')) && filled(config('cashier.secret')), 'STRIPE_KEY and STRIPE_SECRET');
        $add('Webhook secret', filled(config('cashier.webhook.secret')), 'STRIPE_WEBHOOK_SECRET (without it /webhooks/stripe-platform refuses every call)');
        $add('Currency', filled(config('cashier.currency')), 'CASHIER_CURRENCY = '.config('cashier.currency'));

        $free = config('plans.free');
        $add('Free plan', is_array(config("plans.plans.{$free}")), "plans.free = {$free}");

        $seen = [];
        foreach ((array) config('plans.plans') as $key => $plan) {
            foreach ($plan['prices'] as $period => $id) {
                $name = "Price id: {$plan['name']} ({$period})";

                if (blank($id)) {
                    $add($name, false, 'not set in the environment');
                } elseif (isset($seen[$id])) {
                    $add($name, false, "same id as {$seen[$id]}: one price could not tell two plans apart");
                } else {
                    $seen[$id] = "{$plan['name']} ({$period})";
                    $add($name, true, $id);
                }
            }
        }

        return $checks;
    }
}
