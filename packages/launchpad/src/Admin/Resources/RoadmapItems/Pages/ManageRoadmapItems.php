<?php

namespace Launchpad\Admin\Resources\RoadmapItems\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\RoadmapItems\RoadmapItemResource;

class ManageRoadmapItems extends ManageRecords
{
    protected static string $resource = RoadmapItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
