<?php

namespace Launchpad\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\WorkspaceScope;
use LogicException;

/**
 * For a model whose rows are one workspace's own (a table made with `$table->belongsToWorkspace()`). Every query is narrowed to the current
 * workspace, and a new row is given it. Outside a request for a workspace (a job, a command) nothing is narrowed, and a row must be given its
 * `workspace_id` by hand: use `Model::withoutGlobalScope(WorkspaceScope::class)` when you mean every workspace's rows.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            $model->workspace_id ??= app(CurrentWorkspace::class)->workspace?->getKey();

            if ($model->workspace_id === null) {
                throw new LogicException(static::class.' needs a workspace: there is none current and no workspace_id was given.');
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
