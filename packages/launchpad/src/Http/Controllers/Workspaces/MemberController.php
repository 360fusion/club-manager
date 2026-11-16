<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\Membership;
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
        $members = $workspace->memberships()->with('user')->get();
        $invitations = $workspace->invitations()->active()->get();
        $role = $current->role();
        // every team the person is in, for the "Working in" row
        $teams = $request->user()->memberships()->with('workspace')->get()->map(fn ($m) => ['name' => $m->workspace->labelFor($request->user()), 'slug' => $m->workspace->slug, 'current' => $m->workspace_id === $workspace->id])->values();
        $permissionLabels = fn (?array $ticks) => collect((array) $ticks)->filter()->keys()->map(fn ($key) => config("launchpad.workspaces.permissions.{$key}.0"))->filter()->values()->all();

        return Ui::render('workspaces.members', [
            'workspace' => $workspace,
            'role' => $role,
            'me' => $current->membership,
            'permissions' => (array) config('launchpad.workspaces.permissions'),
            'roleMatrix' => RoleMatrix::rows($workspace),
            'members' => $members,
            'invitations' => $invitations,
            'teams' => $teams,
            'permissionLabels' => $permissionLabels,
            'assignable' => Role::assignable(),
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'role' => $role->value,
            'canManageMembers' => $current->can(Ability::ManageMembers),
            'canManageWorkspace' => $current->can(Ability::ManageWorkspace),
            'isOwner' => $role === Role::Owner,
            'members' => $members->map(fn ($m) => ['id' => $m->id, 'name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value,
                'roleLabel' => $m->role->label(), 'canManage' => $current->can(Ability::ManageMembers) && $role->outranks($m->role),
                'permissions' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => $m->can($key))->all()])->values(),
            'roleMatrix' => RoleMatrix::rows($workspace),
            'editableRoles' => array_map(fn (Role $r) => $r->value, $role->editableRoles()),
            'permissionList' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'about' => $p[1]])->values(),
            'teams' => $teams,
            'invitations' => $invitations->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'roleLabel' => $i->role->label(), 'permissions' => $permissionLabels($i->permissions)])->values(),
            'assignable' => collect(Role::assignable())->filter(fn ($r) => $role === Role::Owner || $r !== Role::Admin)
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
        ]);
    }

    public function update(Request $request, string $slug, Membership $membership, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        abort_unless($membership->workspace_id === $current->workspace->id, 404);
        $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        $workspaces->changeRole($current->workspace, $membership, Role::from($request->string('role')->toString()), $request->user());

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
