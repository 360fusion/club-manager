<?php

namespace App\Providers;

use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal;
use App\Domains\ClubAccounting\Livewire\Committee\CreateCommitteeMeetingModal;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Http\Middleware\EnsureUserCanAdministerClub;
use App\Models\Club;
use App\Models\DefaultEmailTemplate;
use App\Models\PaddleSubscription;
use App\Models\PaddleSubscriptionItem;
use App\Support\Currencies;
use App\Support\ReservedClubSlugs;
use App\Support\SafeFileAdder;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Paddle\Cashier;
use Laravel\Pennant\Feature;
use Launchpad\Launchpad;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // club-manager has its own users columns and tables; it takes only the Launchpad migrations it needs (database/migrations)
        Launchpad::ignoreMigrations();

        // Both Cashier packages register a `stripe/webhook` route, and the Paddle one answers it with a controller
        // meant for a different provider. Lodges take card payments on their own Stripe account through
        // /webhooks/stripe/{club}, so neither package's routes are used.
        \Laravel\Cashier\Cashier::ignoreRoutes();
        Cashier::ignoreRoutes();

        // Uploaded files keep an extension that matches their content (see SafeFileAdder).
        $this->app->bind(FileAdder::class, SafeFileAdder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // When someone last signed in on this session: turning on Face ID sign-in soon after needs no password again.
        Event::listen(Login::class, fn () => session()->put('auth.signed_in_at', time()));

        // `composer dev`'s worker runs the email and newsletter queues too (the default one only takes `default`). Not
        // `sites`: a website build refuses to run while the Vite dev server does, so start that worker by hand.
        DevCommands::artisan('queue:listen --queue=transactional,default,bulk --tries=1 --timeout=0', 'queue');

        // Livewire views get the club's currency symbol ($cs) and code ($cc) from the component's public clubSlug.
        View::composer('livewire.*', function ($view) {
            static $clubs = [];

            $data = $view->getData();

            // A component that already supplies its own symbol (no public clubSlug) keeps it.
            if (array_key_exists('cs', $data)) {
                return;
            }

            $slug = $data['clubSlug'] ?? null;

            if (is_string($slug) && ! array_key_exists($slug, $clubs)) {
                $clubs[$slug] = Club::with('province.grandLodge')->where('slug', $slug)->first();
            }

            $club = is_string($slug) ? $clubs[$slug] : null;

            $view->with('cs', Currencies::symbolFor($club))->with('cc', $club?->currencyCode() ?? Currencies::DEFAULT);
        });

        // The committee and accounting layout names the club in the URL, reusing the one the admin check already loaded.
        View::composer('components.layouts.app', function ($view) {
            $club = $view->getData()['club'] ?? null;

            if (! $club instanceof Club) {
                $slug = request()->route('clubSlug') ?? request()->route('slug');
                $club = is_string($slug) ? (request()->attributes->get("club:{$slug}") ?? Club::where('slug', $slug)->first()) : null;
            }

            $view->with('currentClub', $club);
        });

        // Longer passwords that have not appeared in known breaches (needs outbound access, so production only).
        Password::defaults(fn () => $this->app->isProduction() ? Password::min(10)->uncompromised() : Password::min(8));

        // Club slugs sit at the top level of the URL space, so they may not
        // shadow fixed paths such as /login or /members.
        Route::pattern('slug', ReservedClubSlugs::routeRegex());
        Route::pattern('clubSlug', ReservedClubSlugs::routeRegex());

        // Livewire updates post to /livewire/update, so repeat the club gate for them.
        Livewire::addPersistentMiddleware([EnsureUserCanAdministerClub::class]);

        // Sign-in and public forms are open to the world, so each has its own limit.
        RateLimiter::for('newsletter', fn () => Limit::perSecond(max(1, (int) config('newsletters.per_second'))));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            // One address trying many accounts (password spraying) is held back too.
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
        // Fortify's POST /two-factor-challenge is throttled by this name; FortifyServiceProvider is not registered, so it must exist here.
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id', $request->ip())));
        RateLimiter::for('api-token', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            // One address trying many accounts (password spraying) is held back too.
            Limit::perMinute(30)->by('api-token-ip|'.$request->ip()),
        ]);
        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        // Contact forms send email to the lodge, so they are also held to an hourly limit per address and per sender.
        RateLimiter::for('contact-form', fn (Request $request) => [
            Limit::perMinute(6)->by('contact|'.$request->ip()),
            Limit::perHour(20)->by('contact-hour|'.$request->ip()),
            Limit::perHour(5)->by('contact-sender|'.Str::lower((string) $request->input('email'))),
        ]);

        if (class_exists(Livewire::class)) {
            Livewire::component(
                'committee.agenda-pack-preview-modal',
                AgendaPackPreviewModal::class
            );
            Livewire::component(
                'committee.create-committee-meeting-modal',
                CreateCommitteeMeetingModal::class
            );
            Livewire::component(
                'members.member-index',
                MemberIndex::class
            );
            Livewire::component(
                'members.member-profile',
                MemberProfile::class
            );
            Livewire::component(
                'candidates.candidate-pipeline',
                CandidatePipeline::class
            );
            Livewire::component(
                'subscriptions.subscription-index',
                SubscriptionIndex::class
            );
            Livewire::component(
                'banking.bank-accounts-index',
                BankAccountsIndex::class
            );
            Livewire::component(
                'banking.bank-import-index',
                BankImportIndex::class
            );
            Livewire::component(
                'banking.bank-reconciliation-workspace',
                BankReconciliationWorkspace::class
            );
        }

        // Stripe's customer is the club: it is the club that pays the platform.
        \Laravel\Cashier\Cashier::useCustomerModel(Club::class);

        if (class_exists(Cashier::class)) {
            Cashier::useSubscriptionModel(PaddleSubscription::class);
            Cashier::useSubscriptionItemModel(PaddleSubscriptionItem::class);
        }

        if (class_exists(Feature::class)) {
            Feature::define('3-course-dining', function ($scope) {
                if ($scope instanceof Club) {
                    return $scope->hasModule('dining_menu');
                }

                return true;
            });

            Feature::define('custom-domain', function ($scope) {
                if ($scope instanceof Club) {
                    return $scope->hasModule('custom_domain');
                }

                return true;
            });

            Feature::define('executive-analytics', function ($scope) {
                if ($scope instanceof Club) {
                    return $scope->hasModule('executive_analytics');
                }

                return true;
            });
        }

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $template = DefaultEmailTemplate::where('template_key', 'password_reset')->first();

            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $memberName = $notifiable->name ?? 'Member';
            $clubName = config('app.name', 'Club Manager');
            $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            $subject = $template?->subject ?? '{{club_name}} - Reset Your Account Password';
            $bodyHtml = $template?->body_html ?? '<p>Dear {{member_name}},</p><p>We received a request to reset the password for your account.</p><p><a href="{{reset_url}}">Reset Password</a></p>';

            $replacements = [
                '{{member_name}}' => e($memberName),
                '{{club_name}}' => e($clubName),
                '{{reset_url}}' => $resetUrl,
                '{{expire_minutes}}' => $expireMinutes,
            ];

            $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
            $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyHtml);

            return (new MailMessage)
                ->subject($subject)
                ->line(new HtmlString($bodyHtml));
        });
    }
}
