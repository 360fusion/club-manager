<?php

namespace Launchpad\Health;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Files\Files;
use Launchpad\Launchpad;
use Launchpad\Models\Workspace;
use Launchpad\Site\Pages;
use Launchpad\Site\Site;
use Launchpad\Support\Locales;

/** The checks behind `launchpad:doctor` and the admin's System health page: is the app set up to take payments and to be put live */
final class Checks
{
    /** @return list<array{name: string, ok: bool, detail: string}> */
    public static function run(bool $remote = false, bool $running = false): array
    {
        $catalog = app(PlanCatalog::class);
        $gateway = app(Gateway::class);

        $checks = [];
        $add = function (string $name, bool $ok, string $detail) use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
        };

        $add('Stripe keys', filled(config('cashier.key')) && filled(config('cashier.secret')), 'STRIPE_KEY and STRIPE_SECRET');
        $add('Webhook secret', filled(config('cashier.webhook.secret')), 'STRIPE_WEBHOOK_SECRET (without it the webhook refuses every call)');
        $add('Currency', filled(config('cashier.currency')), 'CASHIER_CURRENCY = '.config('cashier.currency'));

        try {
            $catalog->free();
            $add('Free plan', true, config('launchpad.billing.free_plan'));
        } catch (\LogicException $e) {
            $add('Free plan', false, $e->getMessage());
        }

        $seen = [];
        foreach ($catalog->all() as $plan) {
            foreach ($plan->prices as $period => $price) {
                $id = $price['id'] ?? null;
                $name = "Price id: {$plan->name} ({$period})";

                if (blank($id)) {
                    $add($name, false, 'not set in the environment');
                } elseif (isset($seen[$id])) {
                    $add($name, false, "same id as {$seen[$id]}: one price could not tell two plans apart");
                } else {
                    $seen[$id] = "{$plan->name} ({$period})";
                    $add($name, true, $id);
                }
            }
        }

        // the website: a shipped legal page that nobody has read, a hole in a page, or no company name is fine on a laptop and not on a live site
        $live = app()->isProduction();
        $pages = app(Pages::class);
        $note = $live ? '' : ' (not enforced outside production)';

        // each listed language has its own legal files and its own review: a translated page still at `template` must not go live unnoticed
        $original = $default = Locales::default();   // setLocale() moves config app.locale, so the default is read once
        try {
            foreach (array_unique([$default, ...array_keys(Locales::supported())]) as $locale) {
                app()->setLocale($locale);
                $suffix = $locale === $default ? '' : " [{$locale}]";

                foreach ((array) config('launchpad.site.legal') as $slug) {
                    // a language with no file of its own is served the default one, which is checked already
                    if (! config("launchpad.site.pages.{$slug}") || ! ($page = $pages->find($slug)) || ($suffix !== '' && $page->locale !== $locale)) {
                        continue;
                    }

                    $reviewed = $pages->status($page) !== 'template';
                    $add("Legal page: {$page->title}{$suffix}", ! $live || $reviewed, ($reviewed ? 'reviewed' : ($suffix === '' ? 'still the template: read it, then mark it reviewed in the admin' : "still the template: read it, then set status: reviewed in your own copy of the {$locale} file")).($reviewed ? '' : $note));
                }
            }
        } finally {
            app()->setLocale($original);
        }

        $takesLocale = count(Locales::supported()) < 2 || is_a(Launchpad::userModel(), HasLocalePreference::class, true);
        $add('Mail in the person\'s language', $takesLocale, $takesLocale ? 'the user model knows the person\'s language' : 'several languages are listed, but '.Launchpad::userModel().' does not implement HasLocalePreference (Illuminate\\Contracts\\Translation\\HasLocalePreference): add it, or queued mail is not sent in the person\'s language');

        $holes = collect($pages->slugs())->filter(fn ($slug) => config("launchpad.site.pages.{$slug}") && $pages->find($slug)?->missingTokens)->all();
        $add('Page tokens filled in', ! $live || $holes === [], ($holes === [] ? 'every {{token}} has a value' : 'no value for a token on: '.implode(', ', $holes).'. Fill them in Site settings').($holes && ! $live ? $note : ''));
        $add('Company and support email', ! $live || (filled(Site::get('company')) && filled(Site::get('support_email'))), (filled(Site::get('company')) && filled(Site::get('support_email')) ? 'set' : 'set the company and the support email in Site settings').$note);

        $add('Debug mode off', ! $live || ! config('app.debug'), 'APP_DEBUG = '.(config('app.debug') ? 'true' : 'false').$note);
        $add('Mail is sent', ! $live || ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER = '.config('mail.default').$note);

        $add('App key', filled(config('app.key')), 'APP_KEY (php artisan key:generate --force)');

        $url = (string) config('app.url');
        $add('App URL', ! $live || (str_starts_with($url, 'https://') && ! preg_match('~//(localhost|127(\.\d+){3}|[^/]*\.test)(:|/|$)~', $url)), "APP_URL = {$url}: the https address of the site, because invitation, sign-in and Stripe links are made from it".$note);
        $add('Queue worker driver', ! $live || config('queue.default') !== 'sync', 'QUEUE_CONNECTION = '.config('queue.default').' (sync runs webhooks and mail inside the request)'.$note);
        $add('Session cookie is secure', ! $live || config('session.secure') === true, 'SESSION_SECURE_COOKIE = '.var_export(config('session.secure'), true).$note);
        $add('Mail from address', ! $live || ! in_array(config('mail.from.address'), [null, '', 'hello@example.com'], true), 'MAIL_FROM_ADDRESS = '.config('mail.from.address').$note);

        try {
            $missing = array_values(array_filter([(new Workspace)->getTable(), 'personal_access_tokens', 'features', 'workspace_files', ...(config('launchpad.modules.media') ? ['media_files'] : [])], fn ($table) => ! Schema::hasTable($table)));
            $add('Migrations have run', $missing === [], $missing === [] ? 'the database has Launchpad\'s tables' : 'no table for: '.implode(', ', $missing).' (php artisan migrate --force)');
        } catch (\Throwable $e) {
            $add('Migrations have run', false, 'the database cannot be reached: '.$e->getMessage());
        }

        if (config('launchpad.accounts.avatar_disk', 'public') === 'public') {
            $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));
            $add('Storage link', ! $live || $linked, ($linked ? 'public/storage is there' : 'public/storage is missing: php artisan storage:link (avatars are served through it)').($linked ? '' : $note));
        }

        if (config('launchpad.modules.files')) {
            try {
                $disk = Storage::disk(Files::disk());
                $disk->put('.launchpad-doctor', 'ok');
                $disk->delete('.launchpad-doctor');
                $add('Files disk is writable', true, Files::disk());
            } catch (\Throwable $e) {
                $add('Files disk is writable', false, Files::disk().': '.$e->getMessage());
            }
        }

        if ($running) {
            $add('Scheduler is running', Heartbeat::seen('scheduler'), Heartbeat::seen('scheduler') ? 'seen in the last five minutes' : 'not seen: run `php artisan schedule:run` every minute (Forge: Scheduler), and check again after a minute. It needs a shared cache (not "array")');
            $add('Queue worker is running', Heartbeat::seen('queue'), Heartbeat::seen('queue') ? 'seen in the last five minutes' : 'not seen: run `php artisan queue:work` as a daemon (Forge: Queue worker); the scheduler sends it a small job every minute');
        }

        if ($remote) {
            foreach ($gateway->health() as $check) {
                $add($check['name'], $check['ok'], $check['detail']);
            }
        }

        return $checks;
    }
}
