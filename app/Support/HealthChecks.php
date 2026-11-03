<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Whether this install is set up to be live: what `php artisan app:doctor` runs after a deploy. Each check says what to
 * fix. Nothing here changes anything.
 */
final class HealthChecks
{
    /**
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public static function run(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail) use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
        };

        $add('Environment', app()->isProduction(), 'APP_ENV is '.app()->environment().' (production on a live site)');
        $add('Debug off', ! config('app.debug'), 'APP_DEBUG must be false: debug pages show settings and secrets');
        $add('App key', filled(config('app.key')), 'APP_KEY (php artisan key:generate)');

        $url = (string) config('app.url');
        $add('App address', str_starts_with($url, 'https://') && ! preg_match('#//(localhost|127\.|[^/]*\.test)#', $url), "APP_URL is {$url}: links in emails are built from it, so it must be the live https address");
        $add('Secure cookies', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true on a site served over https');
        $add('Proxies trusted', Request::getTrustedProxies() !== [], 'TRUSTED_PROXIES when a load balancer or Cloudflare is in front, or the client IP and https are read wrongly');

        $driver = DB::connection()->getDriverName();
        $add('Database', $driver === 'pgsql', "Running on {$driver}. PostgreSQL is the intended database: financial rows are protected from cascade deletes there");

        $add('Mail driver', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER is '.config('mail.default').': with log or array nothing is sent, and sign-up email confirmation is switched off');
        $from = (string) config('mail.from.address');
        $add('Mail from address', filled($from) && ! str_contains($from, 'example.'), "MAIL_FROM_ADDRESS is {$from}");

        $queue = (string) config('queue.default');
        $add('Queue driver', $queue !== 'sync', "QUEUE_CONNECTION is {$queue}: emails are queued, and sync sends them while the visitor waits");

        if ($queue === 'database') {
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            $waiting = $oldest !== null && (time() - (int) $oldest) > 600;
            $add('Queue worker', ! $waiting, $waiting ? 'A job has waited over 10 minutes: start or restart the worker (php artisan queue:work --queue=transactional,default,bulk)' : 'Nothing is waiting. The worker must run --queue=transactional,default,bulk');
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $add('Failed jobs', $failed === 0, "{$failed} job(s) failed in the last day (php artisan queue:failed)");

        $migrator = app('migrator');
        $pending = array_diff(array_keys($migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')])), $migrator->getRepository()->getRan());
        $add('Migrations', $pending === [], $pending === [] ? 'All have run' : count($pending).' not run yet (php artisan migrate --force)');

        $add('Security contact', filled(config('app.security_contact')), 'SECURITY_CONTACT_EMAIL: shown at /.well-known/security.txt so people know where to report a problem');
        $add('Storage link', is_link(public_path('storage')) || is_dir(public_path('storage')), 'php artisan storage:link: uploaded photos and files are served from it');

        if (config('platform_payments.enabled')) {
            $missing = array_keys(array_filter([
                'PLATFORM_STRIPE_SECRET' => blank(config('platform_payments.stripe_secret')),
                'STRIPE_CONNECT_CLIENT_ID' => blank(config('platform_payments.connect_client_id')),
                'STRIPE_CONNECT_WEBHOOK_SECRET' => blank(config('platform_payments.connect_webhook_secret')),
                'PLATFORM_PAYMENT_TERMS_URL' => blank(config('platform_payments.terms_url')),
            ]));
            $add('Platform payments', $missing === [], $missing === [] ? 'Stripe Connect settings are in' : 'EVENTS_PLATFORM_PAYMENTS is on but these are missing: '.implode(', ', $missing));
        }

        $superadmins = User::where('is_super_admin', true)->get(['id', 'email', 'two_factor_confirmed_at']);
        $without = $superadmins->whereNull('two_factor_confirmed_at')->pluck('email');
        $add('Superadmins', $superadmins->isNotEmpty() && $without->isEmpty(), $superadmins->isEmpty() ? 'There is no superadmin (php artisan superadmin:grant {email})' : ($without->isEmpty() ? $superadmins->count().' with 2FA on' : 'No 2FA yet, so locked out of /superadmin: '.$without->implode(', ')));

        return $checks;
    }
}
