<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Launchpad\Launchpad;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\Role;

class Workspace extends Model
{
    use Billable;

    protected $guarded = [];

    /** launchpad.workspaces.table: an app with teams of its own points Launchpad at that table */
    public function getTable(): string
    {
        return config('launchpad.workspaces.table') ?: parent::getTable();
    }

    protected function casts(): array
    {
        return ['role_permissions' => 'array'];
    }

    /** Where Stripe sends receipts and what the customer is called there */
    public function stripeEmail(): ?string
    {
        return $this->owner?->email;
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'owner_id');
    }

    public function customRoles(): HasMany
    {
        return $this->hasMany(CustomRole::class)->orderBy('name');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** What to call this team for this person: "My own team" for their personal one, "Sam's team" for someone else's personal one, the name otherwise */
    public function labelFor(?object $user): string
    {
        if (! $this->isPersonal()) {
            return $this->name;
        }

        return $user && $this->owner_id === $user->getKey() ? 'My own team' : ($this->owner?->name ?: 'Personal').'\'s team';
    }

    /** What a role may do in this team: what the owner set for it here, and where nothing was set, the defaults (Role for an Ability, config launchpad.workspaces.permissions for a key) */
    public function roleHas(Role $role, Ability|string $ability): bool
    {
        if ($role === Role::Owner) {
            return true;
        }

        $key = $ability instanceof Ability ? $ability->value : $ability;
        $set = ((array) $this->role_permissions)[$role->value][$key] ?? null;

        if ($set !== null) {
            return (bool) $set;
        }

        return self::defaultFor($role, $key);
    }

    /** What the permission would be for the role with nothing set for this team */
    public static function defaultFor(Role $role, string $key): bool
    {
        $ability = Ability::tryFrom($key);

        return $role === Role::Owner || ($ability ? $role->can($ability) : in_array($role->value, (array) config("launchpad.workspaces.permissions.{$key}.2", []), true));
    }

    public function isPersonal(): bool
    {
        return $this->kind === 'personal';
    }
}
