<?php

namespace App\Providers;

use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Livewire\Charity\CharityDashboard;
use App\Domains\ClubAccounting\Livewire\Charity\CharityFestival;
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
use App\Support\ReservedClubSlugs;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Paddle\Cashier;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Club slugs sit at the top level of the URL space, so they may not
        // shadow fixed paths such as /login or /members.
        Route::pattern('slug', ReservedClubSlugs::routeRegex());
        Route::pattern('clubSlug', ReservedClubSlugs::routeRegex());

        // Livewire updates post to /livewire/update, so repeat the club gate for them.
        Livewire::addPersistentMiddleware([EnsureUserCanAdministerClub::class]);

        // Sign-in and public forms are open to the world, so each has its own limit.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('api-token', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth-forms', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

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
            Livewire::component(
                'charity.charity-dashboard',
                CharityDashboard::class
            );
            Livewire::component(
                'charity.charity-festival',
                CharityFestival::class
            );
        }

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
