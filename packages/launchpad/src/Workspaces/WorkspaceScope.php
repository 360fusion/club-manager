<?php

namespace Launchpad\Workspaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Narrows a query to the current workspace (CurrentWorkspace). With none current (a console command, a queue job, the admin) it does nothing. */
final class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspace = app(CurrentWorkspace::class)->workspace;

        if ($workspace !== null) {
            $builder->where($model->qualifyColumn('workspace_id'), $workspace->getKey());
        }
    }
}
