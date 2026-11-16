<?php

namespace Launchpad\Workspaces;

use Launchpad\Models\Workspace;

/** What each workspace role can do, as a table: the rules in Role plus the permissions in config launchpad.workspaces.permissions (before any ticks for a person) */
final class RoleMatrix
{
    /** @return list<array{key: ?string, label: string, about: string, roles: array<string, bool>}> one row per thing, a yes or no for each role */
    public static function rows(?Workspace $workspace = null): array
    {
        $rows = [];

        foreach ([
            [Ability::ViewMembers, 'See the members', 'Open the list of people in the team.'],
            [Ability::ManageWorkspace, 'Change team settings', 'Rename the team and change its settings.'],
            [Ability::Transfer, 'Transfer ownership', 'Hand the team to someone else.'],
            [Ability::Delete, 'Delete the team', 'Remove the team and its data.'],
        ] as [$ability, $label, $about]) {
            $rows[] = ['key' => null, 'label' => $label, 'about' => $about, 'roles' => collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $workspace ? $workspace->roleHas($role, $ability) : $role->can($ability)])->all()];
        }

        foreach ((array) config('launchpad.workspaces.permissions') as $key => [$label, $about, $given]) {
            $rows[] = ['key' => $key, 'label' => $label, 'about' => $about, 'roles' => collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $workspace ? $workspace->roleHas($role, $key) : ($role === Role::Owner || in_array($role->value, (array) $given, true))])->all()];
        }

        return $rows;
    }
}
