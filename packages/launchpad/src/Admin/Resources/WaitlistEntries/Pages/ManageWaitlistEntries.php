<?php

namespace Launchpad\Admin\Resources\WaitlistEntries\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\WaitlistEntries\WaitlistEntryResource;

class ManageWaitlistEntries extends ManageRecords
{
    protected static string $resource = WaitlistEntryResource::class;
}
