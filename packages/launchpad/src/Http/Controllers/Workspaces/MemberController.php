<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\Role;
use Launchpad\Workspaces\RoleMatrix;
use Launchpad\Workspaces\Workspaces;

class MemberController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current): View|Responsable
    {
        $workspace = $current->workspace;
        $members = $workspace->memberships()->with('user', 'customRole')->get();
        $invitations = $workspace->invitations()->active()->with('customRole')->get();
        $role = $current->role();
        // every team the person is in, for the "Working in" row
        $teams = $request->user()->memberships()->with('workspace')->get()->map(fn ($m) => ['name' => $m->workspace->labelFor($request->user()), 'slug' => $m->workspace->slug, 'current' => $m->workspace_id === $workspace->id])->values();
        $roleOptions = $this->roleOptions($workspace, $current->membership);
        $permissionLabels = fn (?array $ticks) => collect((array) $ticks)->filter()->keys()->map(fn ($key) => config("launchpad.workspaces.permissions.{$key}.0"))->filter()->values()->all();

        return Ui::render('workspaces.members', [
            'workspace' => $workspace,
            'role' => $role,
            'me' => $current->membership,
            'permissions' => (array) config('launchpad.workspaces.permissions'),
            'roleMatrix' => RoleMatrix::rows($workspace),
            'matrixColumns' => RoleMatrix::columns($workspace),
            'members' => $members,
            'invitations' => $invitations,
            'teams' => $teams,
            'permissionLabels' => $permissionLabels,
            'assignable' => $roleOptions,
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'role' => $role->value,
            'canManageMembers' => $current->can(Ability::ManageMembers),
            'canManageWorkspace' => $current->can(Ability::ManageWorkspace),
            'isOwner' => $role === Role::Owner,
            'members' => $members->map(fn ($m) => ['id' => $m->id, 'name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value, 'roleValue' => $m->roleValue(),
                'roleLabel' => $m->roleLabel(), 'canManage' => $current->can(Ability::ManageMembers) && $role->outranks($m->role),
                'permissions' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => $m->can($key))->all()])->values(),
            'roleMatrix' => RoleMatrix::rows($workspace),
            'matrixColumns' => RoleMatrix::columns($workspace),
            'editableRoles' => array_map(fn (Role $r) => $r->value, $role->editableRoles()),
            'permissionList' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'about' => $p[1]])->values(),
            'teams' => $teams,
            'invitations' => $invitations->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'roleLabel' => $i->roleLabel(), 'permissions' => $permissionLabels($i->permissions)])->values(),
            'assignable' => $roleOptions,
        ]);
    }

    /**
     * The roles the person may give, for a role menu: the built-in ones below their own (an admin only from the owner) and the team's own roles
     * that sit below them and grant nothing they do not hold.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(Workspace $workspace, Membership $me): array
    {
        $built = collect(Role::assignable())->filter(fn (Role $r) => $me->role === Role::Owner || $r !== Role::Admin)
            ->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()]);
        $own = $workspace->customRoles->filter(fn ($c) => $me->role->outranks($c->base_role) && $c->heldBy($me))
            ->map(fn ($c) => ['value' => 'custom:'.$c->id, 'label' => $c->name]);

        return $built->merge($own)->values()->all();
    }

    public function update(Request $request, string $slug, Membership $membership, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        abort_unless($membership->workspace_id === $current->workspace->id, 404);
        $request->validate(['role' => ['required', 'string']]);
        [$role, $custom] = $workspaces->parseRole($current->workspace, $request->string('role')->toString());

        $workspaces->changeRole($current->workspace, $membership, $role, $request->user(), $custom);

        return back()->with('status', 'Role changed.');
    }

    public function permissions(Request $request, string $slug, Membership $membership, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        abort_unless($membership->workspace_id === $current->workspace->id, 404);

        $ticks = [];
        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            $ticks[$key] = $request->boolean("permissions.{$key}");
        }

        $workspaces->setPermissions($current->workspace, $membership, $ticks, $request->user());

        return back()->with('status', 'Permissions saved.');
    }

    public function destroy(Request $request, string $slug, Membership $membership, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        abort_unless($membership->workspace_id === $current->workspace->id, 404);

        $workspaces->remove($current->workspace, $membership, $request->user());

        return back()->with('status', 'Member removed.');
    }
}
