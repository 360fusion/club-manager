<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Launchpad\Workspaces\Concerns\BelongsToWorkspace;
use Launchpad\Workspaces\WorkspaceScope;

/** One line of a team's activity (Launchpad\Workspaces\Activity writes them): who did what, as a sentence */
class WorkspaceEvent extends Model
{
    use BelongsToWorkspace, Prunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Older than config launchpad.workspaces.activity_days (365): `model:prune` is scheduled daily */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope(WorkspaceScope::class)->where('created_at', '<', now()->subDays((int) config('launchpad.workspaces.activity_days', 365)));
    }
}
