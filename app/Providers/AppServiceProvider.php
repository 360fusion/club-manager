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
    }
}
