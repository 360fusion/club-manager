<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        if (class_exists(\Laravel\Paddle\Cashier::class)) {
            \Laravel\Paddle\Cashier::useSubscriptionModel(\App\Models\PaddleSubscription::class);
            \Laravel\Paddle\Cashier::useSubscriptionItemModel(\App\Models\PaddleSubscriptionItem::class);
        }

        if (class_exists(\Laravel\Pennant\Feature::class)) {
            \Laravel\Pennant\Feature::define('3-course-dining', function ($scope) {
                if ($scope instanceof \App\Models\Club) {
                    return $scope->hasModule('dining_menu');
                }
                return true;
            });

            \Laravel\Pennant\Feature::define('custom-domain', function ($scope) {
                if ($scope instanceof \App\Models\Club) {
                    return $scope->hasModule('custom_domain');
                }
                return true;
            });

            \Laravel\Pennant\Feature::define('executive-analytics', function ($scope) {
                if ($scope instanceof \App\Models\Club) {
                    return $scope->hasModule('executive_analytics');
                }
                return true;
            });
        }
    }
}
