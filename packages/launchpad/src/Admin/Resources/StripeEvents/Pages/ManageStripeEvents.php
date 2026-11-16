<?php

namespace Launchpad\Admin\Resources\StripeEvents\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\StripeEvents\StripeEventResource;

class ManageStripeEvents extends ManageRecords
{
    protected static string $resource = StripeEventResource::class;
}
