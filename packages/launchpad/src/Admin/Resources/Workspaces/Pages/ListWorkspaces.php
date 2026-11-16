<?php

namespace Launchpad\Admin\Resources\Workspaces\Pages;

use Filament\Resources\Pages\ListRecords;
use Launchpad\Admin\Resources\Workspaces\WorkspaceResource;

class ListWorkspaces extends ListRecords
{
    protected static string $resource = WorkspaceResource::class;
}
