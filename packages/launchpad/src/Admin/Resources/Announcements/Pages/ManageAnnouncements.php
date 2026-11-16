<?php

namespace Launchpad\Admin\Resources\Announcements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\Announcements\AnnouncementResource;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
