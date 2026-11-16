<?php

namespace Launchpad\Admin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Launchpad\Admin\Pages\AiAccess;
use Launchpad\Admin\Pages\EmailTemplates;
use Launchpad\Admin\Pages\Features;
use Launchpad\Admin\Pages\PlanLimits;
use Launchpad\Admin\Pages\PromoCodes;
use Launchpad\Admin\Pages\SiteSettings;
use Launchpad\Admin\Pages\SystemHealth;
use Launchpad\Admin\Pages\TeamAddressesPage;
use Launchpad\Admin\Pages\WebsitePages;
use Launchpad\Admin\Resources\AdminActions\AdminActionResource;
use Launchpad\Admin\Resources\Announcements\AnnouncementResource;
use Launchpad\Admin\Resources\BlogPosts\BlogPostResource;
use Launchpad\Admin\Resources\ClosingAccounts\ClosingAccountResource;
use Launchpad\Admin\Resources\ContactMessages\ContactMessageResource;
use Launchpad\Admin\Resources\HelpFeedback\HelpFeedbackResource;
use Launchpad\Admin\Resources\LoggedErrors\LoggedErrorResource;
use Launchpad\Admin\Resources\Reports\ReportResource;
use Launchpad\Admin\Resources\RoadmapItems\RoadmapItemResource;
use Launchpad\Admin\Resources\Staff\StaffResource;
use Launchpad\Admin\Resources\StripeEvents\StripeEventResource;
use Launchpad\Admin\Resources\Subscriptions\SubscriptionResource;
use Launchpad\Admin\Resources\SyncEvents\SyncEventResource;
use Launchpad\Admin\Resources\Users\UserResource;
use Launchpad\Admin\Resources\WaitlistEntries\WaitlistEntryResource;
use Launchpad\Admin\Resources\Workspaces\WorkspaceResource;
use Launchpad\Admin\Widgets\LatestSignups;
use Launchpad\Admin\Widgets\RevenueOverview;
use Launchpad\Admin\Widgets\SignupsChart;
use Launchpad\Admin\Widgets\StatsOverview;

/** Everything the package adds to an admin panel. An app with its own panel adds `LaunchpadPlugin::make()` to it. */
class LaunchpadPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'launchpad';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->defaultAvatarProvider(AvatarProvider::class)
            ->resources([AnnouncementResource::class, UserResource::class, StaffResource::class, ClosingAccountResource::class, WorkspaceResource::class, SubscriptionResource::class, StripeEventResource::class, AdminActionResource::class, ContactMessageResource::class, HelpFeedbackResource::class, SyncEventResource::class, LoggedErrorResource::class, ReportResource::class, BlogPostResource::class, RoadmapItemResource::class, WaitlistEntryResource::class])
            ->pages([AiAccess::class, EmailTemplates::class, Features::class, PlanLimits::class, PromoCodes::class, SiteSettings::class, WebsitePages::class, SystemHealth::class, TeamAddressesPage::class])
            ->widgets([RevenueOverview::class, StatsOverview::class, SignupsChart::class, LatestSignups::class])
            ->navigationItems(StaffTools::navigationItems());
    }

    public function boot(Panel $panel): void {}
}
