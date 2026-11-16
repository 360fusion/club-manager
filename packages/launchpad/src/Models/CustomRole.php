<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Workspaces\Role;

/** A role a team made for itself: a name and permissions on top of a base role (manager or member), which gives it its rank */
class CustomRole extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['base_role' => Role::class, 'permissions' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** What the role gives for a permission key: what was set for it, else what its base role gives in this team */
    public function grants(string $key): bool
    {
        return (bool) ($this->permissions[$key] ?? $this->workspace->roleHas($this->base_role, $key));
    }

    /** Whether this person holds everything the role grants: nobody hands out more than they have */
    public function heldBy(Membership $member): bool
    {
        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            if ($this->grants($key) && ! $member->can($key)) {
                return false;
            }
        }

        return true;
    }
}
