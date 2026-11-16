<?php

namespace Launchpad\Admin\Resources\ClosingAccounts\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\ClosingAccounts\ClosingAccountResource;

class ManageClosingAccounts extends ManageRecords
{
    protected static string $resource = ClosingAccountResource::class;
}
