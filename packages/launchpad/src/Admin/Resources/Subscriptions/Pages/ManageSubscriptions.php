<?php

namespace Launchpad\Admin\Resources\Subscriptions\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\Subscriptions\SubscriptionResource;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;
}
