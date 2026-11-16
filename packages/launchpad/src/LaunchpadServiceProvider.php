<?php

namespace Launchpad;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Laravel\Sanctum\Sanctum;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\SignupSettings;
use Launchpad\Admin\AdminPanelProvider;
use Launchpad\Admin\Impersonation;
use Launchpad\Admin\StaffTools;
use Launchpad\Billing\CashierGateway;
use Launchpad\Billing\Entitlements;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\HandleStripeEvent;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\Vat;
use Launchpad\Console\DoctorCommand;
use Launchpad\Console\HelpBuildCommand;
use Launchpad\Console\InstallCommand;
use Launchpad\Console\LangMissingCommand;
use Launchpad\Console\MakeAdminCommand;
use Launchpad\Console\OpenApiCommand;
use Launchpad\Console\PruneHelpFeedbackCommand;
use Launchpad\Console\PublishCommand;
use Launchpad\Console\PurgeClosedAccountsCommand;
use Launchpad\Console\StatusCommand;
use Launchpad\Console\SyncCommand;
use Launchpad\Features\Flags;
use Launchpad\Health\ErrorLog;
use Launchpad\Health\Heartbeat;
use Launchpad\Http\Controllers\Api\ApiDocsController;
use Launchpad\Http\Controllers\Billing\StripeWebhookController;
use Launchpad\Http\Controllers\LocaleController;
use Launchpad\Http\Middleware\AuthenticateApiToken;
use Launchpad\Http\Middleware\EnsureAccountIsOpen;
use Launchpad\Http\Middleware\EnforceMeter;
use Launchpad\Http\Middleware\EnsureEmailIsVerifiedByPolicy;
use Launchpad\Http\Middleware\EnsureMcpAccess;
use Launchpad\Http\Middleware\ForceJsonResponses;
use Launchpad\Http\Middleware\GuardImpersonation;
use Launchpad\Http\Middleware\Idempotent;
use Launchpad\Http\Middleware\PublicRoute;
use Launchpad\Http\Middleware\ResolveWorkspace;
use Launchpad\Http\Middleware\TeamSubdomains;
use Launchpad\Mcp\LaunchpadServer;
use Launchpad\Http\Middleware\SetLocale;
use Launchpad\Jobs\QueueHeartbeat;
use Launchpad\Mail\Renderer;
use Launchpad\Models\AccountEvent;
use Launchpad\Models\ApiToken;
use Launchpad\Models\ApiTokenUsage;
use Launchpad\Models\LoggedError;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceEvent;
use Launchpad\Site\Consent;
use Launchpad\Support\ConfigMerge;
use Launchpad\Support\SettingsCache;
use Launchpad\Sync\Sync;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\Activity;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\TeamAddresses;
use Launchpad\Workspaces\Invitations;
use Launchpad\Workspaces\Workspaces;
use Throwable;

class LaunchpadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // the package's defaults under whatever the app has set, deep (see ConfigMerge)
        config(['launchpad' => ConfigMerge::merge(require __DIR__.'/../config/launchpad.php', (array) config('launchpad', []))]);

        // staff sign in to the admin on a guard of their own, so a password typed into the site's sign-in page never opens it
        if (config('auth.guards.admin') === null) {
            config(['auth.guards.admin' => ['driver' => 'session', 'provider' => config('auth.guards.web.provider', 'users')]]);
        }

        // one per request, so a long-running worker never carries one request's settings into the next
        $this->app->scoped(SettingsCache::class);
        $this->app->scoped(SignupSettings::class);
        $this->app->scoped(CurrentWorkspace::class);
        $this->app->scoped(PlanCatalog::class);
        $this->app->bind(Gateway::class, CashierGateway::class);

        if (config('launchpad.modules.admin')) {
            $this->app->register(AdminPanelProvider::class);
        }

        if (config('launchpad.modules.billing')) {
            // a workspace is what is billed (a person's personal workspace is their own); the webhook route is ours, which needs a signing secret
            Cashier::useCustomerModel(Workspace::class);
            Cashier::ignoreRoutes();
        }
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('verified.policy', EnsureEmailIsVerifiedByPolicy::class);
        // the gate with no parameter: deny by default for every route behind `auth`, so a new route is covered without anyone remembering
        $router->pushMiddlewareToGroup('web', EnsureEmailIsVerifiedByPolicy::class);
        config('launchpad.modules.admin') && $router->pushMiddlewareToGroup('web', GuardImpersonation::class);
        $router->aliasMiddleware('launchpad.locale', SetLocale::class);
        $router->pushMiddlewareToGroup('web', SetLocale::class);
        $this->socialKeys();
        $router->aliasMiddleware('workspace', ResolveWorkspace::class);
        $router->aliasMiddleware('account.open', EnsureAccountIsOpen::class);
        $router->aliasMiddleware('launchpad.public', PublicRoute::class);
        $router->aliasMiddleware('launchpad.api', AuthenticateApiToken::class);
        $router->aliasMiddleware('launchpad.json', ForceJsonResponses::class);
        $router->aliasMiddleware('launchpad.idempotent', Idempotent::class);
        $router->aliasMiddleware('meter', EnforceMeter::class);

        if (config('launchpad.modules.api')) {
            Sanctum::usePersonalAccessTokenModel(ApiToken::class);
            config(['sanctum.token_prefix' => config('sanctum.token_prefix') ?: config('launchpad.api.token_prefix')]);
        }

        // feature:new-editor, for the current workspace (Pennant's own middleware, which answers 400 when the feature is off)
        $router->aliasMiddleware('feature', EnsureFeaturesAreActive::class);

        // for a table of one workspace's rows (with the BelongsToWorkspace trait on its model)
        Blueprint::macro('belongsToWorkspace', fn () => $this->foreignId('workspace_id')->constrained((new Workspace)->getTable())->cascadeOnDelete());

        // a module that is off leaves its tables out (Modules); an app with tables of its own can leave them all out (Launchpad::ignoreMigrations)
        foreach (Launchpad::runsMigrations() ? glob(__DIR__.'/../database/migrations/*.php') : [] as $migration) {
            Modules::migrates($migration) && $this->loadMigrationsFrom($migration);
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'launchpad');
        // the English sentences are the keys; a build's own lang/<locale>.json is merged over these
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        // @consent('analytics') ... @endconsent: for anything that is not strictly necessary
        Blade::if('consent', fn (string $category) => Consent::granted($category));
        // config launchpad.features, given to Pennant: @feature('new-editor') ... @endfeature and Feature::active() ask about the current workspace
        Flags::register();

        // @can('manage-members') and can:manage-billing for the current workspace: the same answer as Membership::can, for the signed-in person only
        foreach ([...array_column(Ability::cases(), 'value'), ...array_keys((array) config('launchpad.workspaces.permissions'))] as $key) {
            Gate::define($key, function ($user) use ($key) {
                $membership = $this->app->make(CurrentWorkspace::class)->membership;

                return $membership?->user_id === $user->getKey() && $membership->can($key);
            });
        }

        config('launchpad.modules.admin') && $this->app->booted(StaffTools::authorize(...));
        // the passkeys package finds people through the same model as everything else
        config(['passkeys.models.authenticatable' => Launchpad::userModel()]);
        // every reported error also goes to HQ's Errors page; returning nothing lets Laravel log it as usual
        if (config('launchpad.modules.admin') && config('launchpad.admin.record_errors', true) && method_exists($handler = $this->app->make(ExceptionHandler::class), 'reportable')) {
            $handler->reportable(fn (Throwable $e) => ErrorLog::record($e));
        }

        $this->limiters();
        $this->syncHooks();
        config('launchpad.modules.auth') && Renderer::useForAccountMails();

        // once a day; the retention period is checked inside
        $this->callAfterResolving(Schedule::class, fn ($schedule) => $schedule->command('launchpad:purge-closed')->dailyAt('03:15')->onOneServer());
        $this->callAfterResolving(Schedule::class, fn ($schedule) => $schedule->command('model:prune', ['--model' => [WorkspaceEvent::class, ApiTokenUsage::class, LoggedError::class]])->daily()->onOneServer());
        // so `launchpad:doctor --running` can tell the scheduler and a queue worker are alive
        $this->callAfterResolving(Schedule::class, function ($schedule) {
            $schedule->call(fn () => Heartbeat::beat('scheduler'))->name('launchpad-scheduler-heartbeat')->everyMinute();
            $schedule->job(new QueueHeartbeat)->everyMinute();
        });
        $this->workspaceEvents();
        $this->accountEvents();
        $this->billing();

        // a team's own subdomain: the site's links on its main address, assets from the page's host (TeamAddresses)
        if (Modules::enabled('workspaces') && TeamAddresses::enabled()) {
            // on the kernel's group (the router's is copied from it), and before auth, so its redirect to sign in is on the main address too
            $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', TeamSubdomains::class)->prependToMiddlewarePriority(TeamSubdomains::class);
        }

        Route::middleware('web')->group(function () {
            // the one stylesheet of the package's pages, served from here so nothing has to be published
            Route::get('launchpad/launchpad.css', fn () => response(file_get_contents(is_file($own = resource_path('css/vendor/launchpad/launchpad.css')) ? $own : __DIR__.'/../resources/css/launchpad.css'), 200, [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]))->middleware('launchpad.public')->name('launchpad.css');

            // choosing a language works with the auth and site modules off
            Route::post('locale', [LocaleController::class, 'update'])->middleware(['launchpad.public', 'throttle:launchpad-locale'])->name('locale.update');

            if (config('launchpad.modules.auth')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
            }

            if (config('launchpad.modules.workspaces')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/workspaces.php');
            }

            if (config('launchpad.modules.sso') && config('launchpad.modules.workspaces')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');
            }

            if (config('launchpad.modules.billing')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/billing.php');
            }

            if (config('launchpad.modules.blog')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/blog.php');
            }

            if (config('launchpad.modules.roadmap')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/roadmap.php');
            }

            if (config('launchpad.modules.moderation')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/moderation.php');
            }

            if (config('launchpad.modules.help') || config('launchpad.modules.contact')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/help.php');
            }

            if (config('launchpad.modules.admin')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/impersonation.php');
            }

            if (config('launchpad.modules.service_token')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/service.php');
            }

            if (config('launchpad.modules.site')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/site.php');
            }
        });

        // a team's directory, with its own token: no web group either
        if (config('launchpad.modules.sso') && config('launchpad.modules.workspaces') && config('launchpad.modules.scim', true)) {
            Route::middleware('launchpad.json')->group(fn () => $this->loadRoutesFrom(__DIR__.'/../routes/scim.php'));
        }

        // a token, not a session: no web group (no cookies, no CSRF), and its own throttle
        if (config('launchpad.modules.api')) {
            Route::prefix('api/v1')->middleware(['launchpad.json', 'throttle:launchpad-api'])->group(fn () => $this->loadRoutesFrom(__DIR__.'/../routes/api.php'));
            // the API's description and a page that shows it: public, for the API's customers
            Route::get('api/v1/openapi.json', [ApiDocsController::class, 'spec'])->middleware('launchpad.public')->name('launchpad.api.openapi');
            Route::get('api/docs', [ApiDocsController::class, 'page'])->middleware(['web', 'launchpad.public'])->name('launchpad.api.docs');

            // an AI assistant's way in, with the same API keys, abilities, rate limit and meter as the API
            if (config('launchpad.modules.mcp', true)) {
                $metered = array_key_exists('api_calls', (array) config('launchpad.metering.meters')) ? ['meter:api_calls'] : [];
                Mcp::web('mcp', LaunchpadServer::class)->middleware(['launchpad.json', 'throttle:launchpad-api', 'launchpad.api:read', EnsureMcpAccess::class, ...$metered])->name('launchpad.mcp');
                // the GET and DELETE that MCP answers with 405 are open on purpose
                collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'mcp' && ! in_array('POST', $route->methods(), true))->each->middleware('launchpad.public');
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([DoctorCommand::class, PurgeClosedAccountsCommand::class, HelpBuildCommand::class, InstallCommand::class, MakeAdminCommand::class, OpenApiCommand::class, SyncCommand::class, PublishCommand::class, StatusCommand::class, PruneHelpFeedbackCommand::class, LangMissingCommand::class]);
            $this->publishes([__DIR__.'/../config/launchpad.php' => config_path('launchpad.php')], 'launchpad-config');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'launchpad-migrations');
            // to restyle every page: the views (and the one stylesheet) are copied into the app, which then wins over the package's
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/launchpad'), __DIR__.'/../resources/css' => resource_path('css/vendor/launchpad')], 'launchpad-views');
        }
    }

    /** Google, Facebook and Microsoft read config('services.*'); the package fills those from its own config unless the app has set them */
    private function socialKeys(): void
    {
        foreach ((array) config('launchpad.social') as $provider => $keys) {
            config(["services.{$provider}" => array_filter(array_merge(['redirect' => url("/auth/{$provider}/callback")], $keys, (array) config("services.{$provider}")), fn ($value) => $value !== null)]);
        }
    }

    /** With the sync module on, every change another service needs to know of is written to the outbox as it happens */
    private function syncHooks(): void
    {
        if (! config('launchpad.modules.sync')) {
            return;
        }

        $sync = fn (): Sync => $this->app->make(Sync::class);
        $user = Launchpad::userModel();

        // created and updated are separate: a model made in this request still says wasRecentlyCreated on every later save
        $user::created(fn ($u) => $sync()->emit('user.upserted', $sync()->userPayload($u)));
        $user::updated(function ($u) use ($sync) {
            if ($u->wasChanged(['name', 'email', 'email_verified_at', 'staff_role', 'deletion_pending_at', 'legacy_id'])) {
                $sync()->emit('user.upserted', $sync()->userPayload($u));
            }
        });
        $user::deleted(fn ($u) => $sync()->emit('user.deleted', ['id' => (string) $u->getKey(), 'legacy_id' => $u->legacy_id]));

        Workspace::created(fn (Workspace $w) => $sync()->emit('workspace.upserted', $sync()->workspacePayload($w)));
        Workspace::updated(function (Workspace $w) use ($sync) {
            if ($w->wasChanged(['name', 'slug', 'kind', 'owner_id', 'timezone', 'legacy_id'])) {
                $sync()->emit('workspace.upserted', $sync()->workspacePayload($w));
            }
        });
        Workspace::deleted(fn (Workspace $w) => $sync()->emit('workspace.deleted', ['id' => (string) $w->id, 'legacy_id' => $w->legacy_id]));

        Membership::created(fn (Membership $m) => $sync()->emit('member.changed', $sync()->membershipPayload($m)));
        Membership::updated(fn (Membership $m) => $m->wasChanged('role') ? $sync()->emit('member.changed', $sync()->membershipPayload($m)) : null);
        Membership::deleted(fn (Membership $m) => $sync()->emit('member.changed', $sync()->membershipPayload($m, removed: true)));

        // a plan changes when the subscription row does (Cashier writes it from Stripe's webhook), and the workspace's plan is worked out afresh
        $plan = function (Subscription $subscription) use ($sync) {
            $workspace = Workspace::find($subscription->workspace_id);
            $workspace && $sync()->emit('plan.changed', $sync()->planPayload($workspace));
        };
        Subscription::saved($plan);
        Subscription::deleted($plan);

        $this->callAfterResolving(Schedule::class, fn ($schedule) => $schedule->command('launchpad:sync')->everyMinute()->withoutOverlapping()->runInBackground());
    }

    private function billing(): void
    {
        if (! config('launchpad.modules.billing')) {
            return;
        }

        if (Vat::enabled()) {
            Cashier::calculateTaxes();
        }

        // a flag that depends on the plan is worked out again when the plan changes (Cashier writes the subscription from Stripe's webhook)
        $replan = fn (Subscription $subscription) => ($workspace = Workspace::find($subscription->workspace_id)) && Flags::planChanged($workspace);
        Subscription::saved($replan);
        Subscription::deleted($replan);

        // outside the web group: Stripe sends no session and no CSRF token, only a signature
        Route::post(config('cashier.path', 'stripe').'/webhook', [StripeWebhookController::class, 'handleWebhook'])
            ->middleware('launchpad.public')->name('cashier.webhook');

        Event::listen(WebhookReceived::class, HandleStripeEvent::class);
    }

    /** Each sign-in goes on the person's Activity page (not before the migration that makes its table has run) */
    private function accountEvents(): void
    {
        Event::listen(Login::class, fn (Login $event) => ! Impersonation::active() && Schema::hasTable('account_events') && AccountEvent::record($event->user, 'signed_in'));
    }

    private function workspaceEvents(): void
    {
        if (! config('launchpad.modules.workspaces')) {
            return;
        }

        // everyone gets a workspace of their own at sign-up (turn off for an app whose people only ever join one)
        if (config('launchpad.workspaces.personal_on_signup')) {
            Event::listen(Registered::class, fn (Registered $event) => $this->app->make(Workspaces::class)->createPersonal($event->user));
        }

        Activity::register();

        // an invitation waiting for an address is handed over the moment the address is proved
        Event::listen(Verified::class, fn (Verified $event) => $this->app->make(Invitations::class)->acceptWaiting($event->user));
    }

    /** The limits on the public forms, unless the app has set its own */
    private function limiters(): void
    {
        $byAddress = fn (Request $request) => AccountRegistration::normalizeEmail($request->input('email')).'|'.$request->ip();

        foreach ([
            'login' => fn (Request $request) => Limit::perMinute(10)->by('login|'.$request->ip()),
            'register' => fn (Request $request) => [Limit::perMinute(5)->by('register|'.$request->ip()), Limit::perHour(20)->by('register-hour|'.$request->ip())],
            'forgot-password' => fn (Request $request) => [Limit::perHour(5)->by('forgot|'.$byAddress($request)), Limit::perHour(20)->by('forgot-ip|'.$request->ip())],
            'reset-password' => fn (Request $request) => Limit::perMinute(5)->by('reset|'.$byAddress($request)),
            'contact' => fn (Request $request) => [Limit::perHour(5)->by('contact|'.$request->ip()), Limit::perHour(3)->by('contact-address|'.$byAddress($request))],
            'launchpad-locale' => fn (Request $request) => Limit::perMinute(30)->by('locale|'.$request->ip()),
            'help-feedback' => fn (Request $request) => Limit::perHour(30)->by('help-feedback|'.$request->ip()),
            // per token, as many a minute as the token's workspace's plan says (its `api_rate` limit), else config launchpad.api.rate_limit;
            // a request with no token at all is counted by address, so guessing is slowed as well
            'launchpad-api' => function (Request $request) {
                $rate = (int) config('launchpad.api.rate_limit', 60);
                $workspace = $request->bearerToken() ? ApiToken::findToken($request->bearerToken())?->workspace : null;

                return Limit::perMinute($workspace ? $this->app->make(Entitlements::class)->limitOr($workspace, 'api_rate', $rate) : $rate)
                    ->by('api|'.($request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip()));
            },
        ] as $name => $limit) {
            if (RateLimiter::limiter($name) === null) {
                RateLimiter::for($name, $limit);
            }
        }
    }
}
