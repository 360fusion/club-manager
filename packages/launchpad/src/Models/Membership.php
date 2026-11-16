<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Launchpad;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\Role;

class Membership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => Role::class, 'permissions' => 'array'];
    }

    /**
     * Whether the person may do this in the workspace: an owner always, otherwise what was ticked for them, and where nothing was ticked what
     * their role gives (an Ability's own rule in Role, or the roles listed for a key in config launchpad.workspaces.permissions).
     */
    public function can(Ability|string $ability): bool
    {
        $key = $ability instanceof Ability ? $ability->value : $ability;

        if ($this->role === Role::Owner) {
            return true;
        }

        $ticked = ($this->permissions ?? [])[$key] ?? null;

        if ($ticked !== null) {
            return (bool) $ticked;
        }

        // what the role gets in this team (the owner may have changed it), else the defaults
        return $this->workspace->roleHas($this->role, $ability);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel());
    }
}
