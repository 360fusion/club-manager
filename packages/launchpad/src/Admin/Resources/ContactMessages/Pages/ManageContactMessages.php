<?php

namespace Launchpad\Admin\Resources\ContactMessages\Pages;

use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\Resources\ContactMessages\ContactMessageResource;

class ManageContactMessages extends ManageRecords
{
    protected static string $resource = ContactMessageResource::class;
}
