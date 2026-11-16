<?php

namespace Launchpad\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\Money;
use Launchpad\Billing\Revenue;

class RevenueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return config('launchpad.modules.billing') && StaffAccess::allowed('view-billing');
    }

    protected function getStats(): array
    {
        $revenue = app(Revenue::class);
        $mrr = $revenue->mrr();
        $churned = $revenue->churned();

        return [
            Stat::make('Monthly revenue', Money::format($mrr['amount']))
                ->description($mrr['unknown'] ? "List prices; {$mrr['unknown']} on a price not in config left out" : 'At list prices, before discounts'),
            Stat::make('New subscriptions', $revenue->started())->description('Last 30 days'),
            Stat::make('Cancelled', $churned['count'])->description(($churned['rate'] === null ? 'Last 30 days' : "{$churned['rate']}% churn, last 30 days")),
        ];
    }
}
