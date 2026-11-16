<?php

namespace Launchpad\Admin\Resources\SyncEvents\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\SyncEvents\SyncEventResource;

class ManageSyncEvents extends ManageRecords
{
    protected static string $resource = SyncEventResource::class;
}
