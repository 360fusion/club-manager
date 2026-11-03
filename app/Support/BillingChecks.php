<?php

namespace App\Support;

/** The checks behind `billing:doctor`: is the app set up to take a club's subscription, and to be put live */
final class BillingChecks
{
    /** @return list<array{name: string, ok: bool, detail: string, enforced: bool}> */
    public static function run(): array
    {
        $live = app()->isProduction();
        $note = $live ? '' : ' (not enforced outside production)';
        $checks = [];
        // `enforced: false` marks a rule for live sites only: outside production it still reads No, but it does not fail billing:doctor.
        $add = function (string $name, bool $ok, string $detail, bool $enforced = true) use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'enforced' => $enforced];
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

        $add('Security contact', filled(config('app.security_contact')), 'SECURITY_CONTACT_EMAIL (shown at /.well-known/security.txt)'.$note, $live);
        $add('Debug mode off', ! config('app.debug'), 'APP_DEBUG = '.(config('app.debug') ? 'true' : 'false').$note, $live);
        $add('Mail is sent', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER = '.config('mail.default').$note, $live);
        $add('Database is PostgreSQL', config('database.default') === 'pgsql', 'DB_CONNECTION = '.config('database.default').$note, $live);

        return $checks;
    }
}
