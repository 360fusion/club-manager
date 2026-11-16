<?php

namespace Launchpad\Admin\Resources\LoggedErrors\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\LoggedErrors\LoggedErrorResource;

class ManageLoggedErrors extends ManageRecords
{
    protected static string $resource = LoggedErrorResource::class;
}
