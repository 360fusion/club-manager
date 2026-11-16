<?php

namespace Launchpad\Admin\Resources\AdminActions\Pages;

use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Launchpad\Admin\Resources\AdminActions\AdminActionResource;

class ManageAdminActions extends ManageRecords
{
    protected static string $resource = AdminActionResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Admin activity';
    }
}
