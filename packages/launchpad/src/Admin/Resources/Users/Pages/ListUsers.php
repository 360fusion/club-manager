<?php

namespace Launchpad\Admin\Resources\Users\Pages;

use Filament\Resources\Pages\ListRecords;
use Launchpad\Admin\Resources\Users\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
