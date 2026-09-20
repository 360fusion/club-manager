<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\PaddleSubscription;
use App\Models\PaddleSubscriptionItem;
use Illuminate\Support\ServiceProvider;
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
        if (class_exists(Livewire::class)) {
            Livewire::component(
                'committee.agenda-pack-preview-modal',
                \App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal::class
            );
            Livewire::component(
                'committee.create-committee-meeting-modal',
                \App\Domains\ClubAccounting\Livewire\Committee\CreateCommitteeMeetingModal::class
            );
            Livewire::component(
                'members.member-index',
                \App\Domains\ClubAccounting\Livewire\Members\MemberIndex::class
            );
            Livewire::component(
                'members.member-profile',
                \App\Domains\ClubAccounting\Livewire\Members\MemberProfile::class
            );
            Livewire::component(
                'candidates.candidate-pipeline',
                \App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline::class
            );
            Livewire::component(
                'subscriptions.subscription-index',
                \App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex::class
            );
            Livewire::component(
                'banking.bank-accounts-index',
                \App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex::class
            );
            Livewire::component(
                'banking.bank-import-index',
                \App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex::class
            );
            Livewire::component(
                'banking.bank-reconciliation-workspace',
                \App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace::class
            );
            Livewire::component(
                'charity.charity-dashboard',
                \App\Domains\ClubAccounting\Livewire\Charity\CharityDashboard::class
            );
            Livewire::component(
                'charity.charity-festival',
                \App\Domains\ClubAccounting\Livewire\Charity\CharityFestival::class
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

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $template = \App\Models\DefaultEmailTemplate::where('template_key', 'password_reset')->first();

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

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject($subject)
                ->line(new \Illuminate\Support\HtmlString($bodyHtml));
        });
    }
}
