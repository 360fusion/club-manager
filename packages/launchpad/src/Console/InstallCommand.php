<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Launchpad\Install\ModulePatcher;
use Launchpad\Install\UserPatcher;

/**
 * `php artisan launchpad:install` wires Launchpad into a fresh Laravel app: the config, the User model, the error pages, the .env.example keys,
 * the skeleton's welcome route out of the way, and a smoke test. It is safe to run again: what is done is left alone. `--migrate` also runs
 * the migrations.
 */
class InstallCommand extends Command
{
    protected $signature = 'launchpad:install {--migrate : Run the migrations too} {--force : Replace files that already exist} {--vue : Draw the pages with Vue and Inertia (copies the Vue pages into the app)} {--preset= : Which parts to use: full, single-tenant (no teams) or minimal} {--without=* : A module to turn off, as often as needed (billing, sso, blog ...)}';

    protected $description = 'Set up Launchpad in this app';

    private const ENV_BLOCK = <<<'ENV'

# Launchpad
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
CASHIER_CURRENCY=gbp
STRIPE_PRICE_PRO_MONTHLY=
STRIPE_PRICE_PRO_ANNUAL=
STRIPE_PRICE_TEAM_MONTHLY=
STRIPE_PRICE_TEAM_ANNUAL=
LAUNCHPAD_AUTOMATIC_TAX=false
LAUNCHPAD_ADMIN_PATH=admin
LAUNCHPAD_ADMIN_2FA=true
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
TERMS_VERSION=1
ENV;

    public function handle(): int
    {
        // the file the launchpad-config tag publishes, copied here so the install works from wherever base_path() is
        $fresh = ! File::exists(config_path('launchpad.php'));
        if ($fresh || $this->option('force')) {
            File::ensureDirectoryExists(config_path());
            File::copy(__DIR__.'/../../config/launchpad.php', config_path('launchpad.php'));
        }
        $this->line('config/launchpad.php is there.');

        if (($off = $this->modules($fresh)) === null) {
            return self::FAILURE;
        }

        $this->user();
        $this->welcomeRoute();
        $this->errorPages();
        $this->env();
        $this->smokeTest();

        // without Filament's own CSS and scripts the admin is unstyled
        if (config('launchpad.modules.admin')) {
            $this->callSilently('filament:assets');
        }

        // uploaded profile photos are served from public/storage
        $this->callSilently('storage:link');

        // uploaded profile photos are served from the public disk
        $this->callSilently('storage:link');

        if ($this->option('vue')) {
            $this->vue();
        }

        // this run read the config before the modules were chosen, so it would still make every table: a new run of artisan reads the new one
        if ($this->option('migrate') && $off === []) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('Launchpad is installed. Next:');
        $this->line('  1. Edit config/launchpad.php: the site name, the plans, the pages you want on.');
        $this->line('  2. php artisan migrate'.($this->option('migrate') && $off === [] ? ' (done)' : ''));
        $this->line('  3. php artisan launchpad:make-admin you@example.com   (the first member of staff)');
        $this->line('  4. Fill in Site settings in HQ (/hq): the company, the support email.');
        in_array('billing', $off, true) || $this->line('  5. Stripe: follow vendor/360fusion/launchpad/docs/stripe-setup.md, then php artisan launchpad:doctor --remote');
        $this->line('  6. Read the legal pages (/terms, /privacy ...) and mark each reviewed in HQ. Production will not pass launchpad:doctor until you do.');
        $this->line('  7. php artisan test   (a smoke test was added)');

        return self::SUCCESS;
    }

    /**
     * The parts of Launchpad this app uses, written into config/launchpad.php: asked on the first install, or given as --preset and --without.
     * A module turned off leaves its tables out of `migrate`. Returns the modules turned off, or null when the options name something unknown.
     *
     * @return list<string>|null
     */
    private function modules(bool $fresh): ?array
    {
        $preset = $this->option('preset');

        if ($preset === null && $this->option('without') === [] && $fresh && $this->input->isInteractive()) {
            $this->line('full: teams, billing, API, single sign-on and the rest (the default)');
            $this->line('single-tenant: sign-in, accounts, admin and the website, with no teams or billing');
            $this->line('minimal: sign-in and admin only');
            $preset = $this->choice('Which parts of Launchpad does this app use?', array_keys(ModulePatcher::PRESETS), 'full');
        }

        $path = config_path('launchpad.php');
        $source = File::exists($path) ? File::get($path) : '';
        $off = array_values(array_unique([...ModulePatcher::off($preset ?? 'full'), ...$this->option('without')]));
        $unknown = array_diff($off, ModulePatcher::listed($source));

        if (($preset !== null && ! array_key_exists($preset, ModulePatcher::PRESETS)) || $unknown !== []) {
            $this->error('Unknown '.($unknown !== [] ? 'module: '.implode(', ', $unknown) : "preset: {$preset}").'. Presets: '.implode(', ', array_keys(ModulePatcher::PRESETS)).'. Modules: the keys of "modules" in config/launchpad.php.');

            return null;
        }

        if ($off !== []) {
            File::put($path, ModulePatcher::patch($source, array_fill_keys($off, false)));
            $this->line('config/launchpad.php: off: '.implode(', ', $off).'. Run php artisan migrate afterwards: their tables are left out.');
        }

        return $off;
    }

    private function user(): void
    {
        $path = app_path('Models/User.php');

        if (! File::exists($path)) {
            $this->warn('app/Models/User.php was not found: add the traits yourself (see docs/install.md).');

            return;
        }

        $source = File::get($path);

        if (UserPatcher::isPatched($source)) {
            $this->line('app/Models/User.php already has Launchpad.');

            return;
        }

        $patched = UserPatcher::patch($source);

        if ($patched === null) {
            $this->warn('app/Models/User.php is not one this can change. Add by hand:');
            $this->line('  implements '.implode(', ', UserPatcher::INTERFACES));
            $this->line('  use '.implode(', ', UserPatcher::TRAITS).';');

            return;
        }

        File::put($path, $patched);
        $this->line('app/Models/User.php: traits and interfaces added.');

        // put the file in the app's own style (the import order, the trait order), if the app uses Pint: that one file only
        if (File::exists(base_path('vendor/bin/pint'))) {
            Process::path(base_path())->run([PHP_BINARY, 'vendor/bin/pint', 'app/Models/User.php']);
        }
    }

    /** Laravel's own "/" shows its welcome page and, being registered later, would hide Launchpad's home: it goes */
    private function welcomeRoute(): void
    {
        $path = base_path('routes/web.php');
        $source = File::exists($path) ? File::get($path) : '';
        $pattern = "/Route::get\('\/', function \(\) \{\s*return view\('welcome'\);\s*\}\);\R?/";

        if (preg_match($pattern, $source)) {
            File::put($path, preg_replace($pattern, '', $source));
            $this->line("routes/web.php: Laravel's welcome route removed (Launchpad has a home page; add your own route for / to replace it).");
        }
    }

    private function errorPages(): void
    {
        foreach (File::files(__DIR__.'/../../resources/stubs/errors') as $file) {
            $to = resource_path('views/errors/'.$file->getFilename());

            if (File::exists($to) && ! $this->option('force')) {
                continue;
            }

            File::ensureDirectoryExists(dirname($to));
            File::copy($file->getPathname(), $to);
        }

        $this->line('resources/views/errors: 403, 404, 419, 429, 500 and 503 pages.');
    }

    private function env(): void
    {
        foreach ([base_path('.env.example')] as $path) {
            if (File::exists($path) && ! str_contains(File::get($path), '# Launchpad')) {
                File::append($path, self::ENV_BLOCK."\n");
                $this->line('.env.example: Launchpad keys added (copy the ones you need into .env).');
            }
        }
    }

    /**
     * The Vue pages: copied into the app (resources/js, the root view, the Inertia middleware), which then owns them, one file at a time: a file that is
     * already there is kept, so a page the app has restyled is never overwritten (`--force` replaces). What cannot be done from here is printed.
     */
    private function vue(): void
    {
        $from = __DIR__.'/../../stubs/vue';
        $copied = 0;
        $kept = 0;

        foreach (File::allFiles($from) as $file) {
            $to = base_path($file->getRelativePathname());

            if (File::exists($to) && ! $this->option('force') && ! self::untouchedSkeleton($file->getRelativePathname(), File::get($to))) {
                $kept++;

                continue;
            }

            File::ensureDirectoryExists(dirname($to));
            File::copy($file->getPathname(), $to);
            $copied++;
        }

        $this->line("Vue pages: {$copied} files copied, {$kept} already there and kept.");

        $bootstrap = base_path('bootstrap/app.php');
        $source = File::exists($bootstrap) ? File::get($bootstrap) : '';

        if (! str_contains($source, 'HandleInertiaRequests')) {
            $patched = preg_replace('/(->withMiddleware\(function \(Middleware \$middleware\)[^{]*\{\R)/', "$1        \$middleware->web(append: [\\App\\Http\\Middleware\\HandleInertiaRequests::class]);\n", $source, 1, $count);

            if ($count === 1) {
                File::put($bootstrap, $patched);
                $this->line('bootstrap/app.php: the Inertia middleware is in the web group.');
            } else {
                $this->warn('bootstrap/app.php: add \\App\\Http\\Middleware\\HandleInertiaRequests::class to the web middleware yourself.');
            }
        }

        $config = base_path('config/launchpad.php');
        if (File::exists($config) && str_contains(File::get($config), "'ui' => env('LAUNCHPAD_UI', 'blade')")) {
            File::put($config, str_replace("'ui' => env('LAUNCHPAD_UI', 'blade')", "'ui' => env('LAUNCHPAD_UI', 'inertia')", File::get($config)));
        }
        $env = base_path('.env.example');
        if (File::exists($env) && ! str_contains(File::get($env), 'LAUNCHPAD_UI')) {
            File::append($env, "LAUNCHPAD_UI=inertia\n");
        }

        $this->newLine();
        $this->warn('To finish the Vue setup:');
        $this->line('  composer require inertiajs/inertia-laravel');
        $this->line('  npm install vue @inertiajs/vue3 && npm install -D @vitejs/plugin-vue');
        $this->line("  vite.config.js: import vue from '@vitejs/plugin-vue', add vue() to plugins, and use input: ['resources/js/app.js'] (plus your own css)");
        $this->line('  set LAUNCHPAD_UI=inertia in .env, then npm run build');
    }

    /** Laravel's own resources/js/app.js of a new app is (nearly) empty: keeping it would build a page that never starts Vue */
    public static function untouchedSkeleton(string $path, string $contents): bool
    {
        return $path === 'resources/js/app.js' && in_array(trim($contents), ['', '//', "import './bootstrap';"], true);
    }

    private function smokeTest(): void
    {
        $to = base_path('tests/Feature/LaunchpadSmokeTest.php');

        if (File::exists($to) && ! $this->option('force')) {
            return;
        }

        File::ensureDirectoryExists(dirname($to));
        File::copy(__DIR__.'/../../stubs/tests/LaunchpadSmokeTest.php', $to);
        $this->line('tests/Feature/LaunchpadSmokeTest.php added.');
    }
}
