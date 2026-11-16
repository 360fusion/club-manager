<?php

namespace Launchpad\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Modules;
use Launchpad\Workspaces\Role;

/** Add to the User model beside HasLaunchpadAccount */
trait HasWorkspaces
{
    // Sanctum's tokens: a workspace's API tokens are made by (and act as) a person
    use HasApiTokens;

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'memberships')->withPivot('role')->withTimestamps();
    }

    public function currentWorkspace(): ?Workspace
    {
        if (! Modules::enabled('workspaces')) {
            return null;
        }

        return $this->workspaces()->whereKey($this->current_workspace_id)->first() ?? $this->workspaces()->first();
    }

    public function roleIn(Workspace $workspace): ?Role
    {
        return $this->memberships()->where('workspace_id', $workspace->id)->first()?->role;
    }
}
