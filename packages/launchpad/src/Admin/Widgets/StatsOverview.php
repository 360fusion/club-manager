<?php

namespace Launchpad\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Cashier\Subscription;
use Launchpad\Admin\StaffAccess;
use Launchpad\Launchpad;
use Launchpad\Models\Workspace;
use Launchpad\Modules;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return StaffAccess::allowed('view-accounts');
    }

    protected function getStats(): array
    {
        $users = Launchpad::userModel();

        return array_values(array_filter([
            Stat::make('Accounts', $users::count())->description($users::whereNotNull('email_verified_at')->count().' verified'),
            Modules::enabled('workspaces') ? Stat::make('Teams', Workspace::where('kind', 'business')->count())->description(Workspace::where('kind', 'personal')->count().' personal') : null,
            Modules::enabled('billing') ? Stat::make('Paying', Subscription::where('stripe_status', 'active')->count())->description(Subscription::where('stripe_status', 'past_due')->count().' past due') : null,
        ]));
    }
}
