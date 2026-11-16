<?php

namespace Launchpad\Admin\Resources\BlogPosts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\Resources\BlogPosts\BlogPostResource;

class ManageBlogPosts extends ManageRecords
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['author_id' => AdminLog::actor()?->getKey()])];
    }
}
