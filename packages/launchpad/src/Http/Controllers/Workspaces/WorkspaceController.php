<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Http\Ui;
use Launchpad\Models\CustomRole;
use Launchpad\Models\Invitation;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Notifications\Notice;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\Role;
use Launchpad\Workspaces\Workspaces;

class WorkspaceController extends Controller
{
    public function index(Request $request): View|Responsable
    {
        $memberships = $request->user()->memberships()->with('workspace')->get();

        return Ui::render('workspaces.index', ['memberships' => $memberships], fn () => [
            'workspaces' => $memberships->map(fn ($m) => ['name' => $m->workspace->labelFor($request->user()), 'slug' => $m->workspace->slug, 'role' => $m->role->label(), 'personal' => $m->workspace->isPersonal(),
                'canSettings' => $m->can(Ability::ManageWorkspace), 'canBilling' => $m->can(Ability::ManageBilling)])->values(),
        ]);
    }

    public function store(Request $request, Workspaces $workspaces): RedirectResponse
    {
        $request->merge(['name' => AccountRegistration::cleanName($request->input('name'))]);
        $request->validate(['name' => ['required', 'string', 'max:120']]);

        $workspace = $workspaces->create($request->user(), $request->input('name'));

        return redirect()->route('workspace.members', $workspace->slug)->with('status', 'Workspace created.');
    }

    /** Makes a workspace the one the person is working in (it must be one they belong to) */
    public function switch(Request $request, string $slug): RedirectResponse
    {
        $membership = Membership::where('user_id', $request->user()->getKey())
            ->whereHas('workspace', fn ($query) => $query->where('slug', $slug))->firstOrFail();

        $request->user()->forceFill(['current_workspace_id' => $membership->workspace_id])->save();

        return redirect()->route('workspace.members', $slug);
    }

    public function settings(CurrentWorkspace $current): View|Responsable
    {
        $workspace = $current->workspace;

        // what each role may do in this team, one card per role the owner can change
        $roles = collect([Role::Admin, Role::Manager, Role::Member])->map(fn (Role $role) => [
            'value' => $role->value, 'label' => $role->label(), 'about' => $role->description(),
            'permissions' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => $workspace->roleHas($role, $key))->all(),
        ])->values();
        $permissionList = collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'about' => $p[1]])->values();

        // the team's own roles, and the built-in ones this person may base a new one on
        $bases = $this->customBases($current);
        $customBases = array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], $bases);
        $customRoles = $workspace->customRoles->map(fn (CustomRole $c) => [
            'id' => $c->id, 'name' => $c->name, 'base' => $c->base_role->value, 'baseLabel' => $c->base_role->label(), 'canEdit' => in_array($c->base_role, $bases, true),
            'permissions' => collect((array) config('launchpad.workspaces.permissions'))->map(fn ($p, $key) => $c->grants($key))->all(),
        ])->values();

        return Ui::render('workspaces.settings', ['workspace' => $workspace, 'role' => $current->role(), 'roles' => $roles, 'permissionList' => $permissionList, 'editable' => $current->role()->editableRoles(), 'customRoles' => $customRoles, 'customBases' => $customBases], fn () => [
            'customRoles' => $customRoles,
            'customBases' => $customBases,
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug, 'timezone' => $workspace->timezone, 'personal' => $workspace->isPersonal()],
            'isOwner' => $current->role() === Role::Owner,
            'editableRoles' => array_map(fn (Role $r) => $r->value, $current->role()->editableRoles()),
            'roles' => $roles,
            'permissionList' => $permissionList,
        ]);
    }

    /**
     * Saves what each role may do in this team. An owner changes the admin, manager and member columns, an admin the manager and member ones, and
     * nobody turns on what they do not hold themselves. Only what differs from the defaults is kept, so later changes to the defaults still reach the team.
     */
    public function updateRoles(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $editable = $current->role()->editableRoles();
        abort_if($editable === [], 403);

        $set = (array) $current->workspace->role_permissions;

        foreach ($editable as $role) {
            unset($set[$role->value]);

            foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
                $on = $request->boolean("roles.{$role->value}.{$key}");

                if ($on && ! $current->workspace->roleHas($role, $key) && ! $current->can($key)) {
                    return back()->withErrors(['roles' => 'You can only turn on permissions you have yourself.'])->withInput();
                }

                if ($on !== Workspace::defaultFor($role, $key)) {
                    $set[$role->value][$key] = $on;
                }
            }
        }

        $current->workspace->update(['role_permissions' => array_filter($set) ?: null]);

        return $this->backToRoles($request, $current)->with('status', 'What each role can do is saved.');
    }

    /** @return list<Role> the roles a custom role may sit on for this person: manager or member, and only ones they may change the permissions of */
    private function customBases(CurrentWorkspace $current): array
    {
        return array_values(array_filter($current->role()->editableRoles(), fn (Role $r) => in_array($r, [Role::Manager, Role::Member], true)));
    }

    public function storeCustomRole(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        abort_if($this->customBases($current) === [], 403);

        $max = (int) config('launchpad.workspaces.max_custom_roles');

        if ($current->workspace->customRoles()->count() >= $max) {
            return back()->withErrors(['custom_role' => "A team can have up to {$max} roles of its own. Remove one to add another."])->withInput();
        }

        return $this->saveCustomRole($request, $current, null);
    }

    public function updateCustomRole(Request $request, string $slug, CustomRole $customRole, CurrentWorkspace $current): RedirectResponse
    {
        abort_unless($customRole->workspace_id === $current->workspace->id, 404);
        abort_unless(in_array($customRole->base_role, $this->customBases($current), true), 403);

        return $this->saveCustomRole($request, $current, $customRole);
    }

    /**
     * A role of the team's own: a name, and the permissions it grants on top of its base role (manager or member, which gives its rank and is
     * fixed once made). Nobody turns on a permission they do not hold themselves. Only what differs from the base role is kept.
     */
    private function saveCustomRole(Request $request, CurrentWorkspace $current, ?CustomRole $role): RedirectResponse
    {
        $workspace = $current->workspace;
        $request->merge(['role_name' => trim((string) $request->input('role_name'))]);
        $data = $request->validate([
            'role_name' => ['required', 'string', 'max:40',
                Rule::unique('custom_roles', 'name')->where('workspace_id', $workspace->id)->ignore($role?->id),
                fn ($attribute, $value, $fail) => collect(Role::cases())->contains(fn (Role $r) => strcasecmp($r->label(), $value) === 0) ? $fail('That is the name of a built-in role.') : null],
            'role_base' => $role ? ['nullable'] : ['required', Rule::in(array_map(fn (Role $r) => $r->value, $this->customBases($current)))],
        ]);
        $base = $role?->base_role ?? Role::from($data['role_base']);

        $set = [];
        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            $on = $request->boolean("permissions.{$key}");

            if ($on && ! ($role ? $role->grants($key) : $workspace->roleHas($base, $key)) && ! $current->can($key)) {
                return back()->withErrors(['custom_role' => 'You can only turn on permissions you have yourself.'])->withInput();
            }

            if ($on !== $workspace->roleHas($base, $key)) {
                $set[$key] = $on;
            }
        }

        $values = ['name' => $data['role_name'], 'permissions' => $set ?: null];
        $role ? $role->update($values) : $workspace->customRoles()->create($values + ['base_role' => $base]);

        return redirect(route('workspace.settings', $workspace->slug).'#custom-roles')->with('status', $role ? 'Role saved.' : 'Role added.');
    }

    /** People holding the role go back to the built-in role it sits on, and keep what was ticked for them personally */
    public function destroyCustomRole(string $slug, CustomRole $customRole, CurrentWorkspace $current): RedirectResponse
    {
        abort_unless($customRole->workspace_id === $current->workspace->id, 404);
        abort_unless(in_array($customRole->base_role, $this->customBases($current), true), 403);

        DB::transaction(function () use ($customRole, $current) {
            // one by one, so each change is synced and the person is told
            Membership::where('custom_role_id', $customRole->id)->with('user')->get()->each(function (Membership $m) use ($customRole, $current) {
                $m->update(['custom_role_id' => null]);
                Notice::to($m->user, 'Your role changed', "The role {$customRole->name} was removed in {$current->workspace->name}. You are now {$m->role->label()}.", route('workspace.members', $current->workspace->slug));
            });
            Invitation::where('custom_role_id', $customRole->id)->update(['custom_role_id' => null]);
            $customRole->delete();
        });

        return redirect(route('workspace.settings', $current->workspace->slug).'#custom-roles')->with('status', 'Role removed.');
    }

    /** Back to the page the form was on, with its edit boxes closed again (the address without ?edit=1), at the roles card */
    private function backToRoles(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $route = $request->input('from') === 'settings' ? 'workspace.settings' : 'workspace.members';

        return redirect(route($route, $current->workspace->slug).'#roles');
    }

    public function resetRoles(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $editable = $current->role()->editableRoles();
        abort_if($editable === [], 403);

        $set = (array) $current->workspace->role_permissions;
        foreach ($editable as $role) {
            unset($set[$role->value]);
        }

        $current->workspace->update(['role_permissions' => array_filter($set) ?: null]);

        return $this->backToRoles($request, $current)->with('status', 'Back to the defaults.');
    }

    public function update(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $request->merge(['name' => AccountRegistration::cleanName($request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        $current->workspace->update($data);

        return back()->with('status', 'Team saved.');
    }

    public function leave(Request $request, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        $workspaces->leave($current->workspace, $request->user());

        return redirect()->route('workspaces.index')->with('status', 'You have left the workspace.');
    }

    public function transfer(Request $request, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        $request->validate(['membership' => ['required', 'integer']]);
        $to = Membership::where(['workspace_id' => $current->workspace->id, 'id' => $request->integer('membership')])->firstOrFail();

        $workspaces->transferOwnership($current->workspace, $to, $request->user());

        return back()->with('status', 'Ownership transferred.');
    }

    public function destroy(Request $request, CurrentWorkspace $current, Workspaces $workspaces): RedirectResponse
    {
        $workspaces->delete($current->workspace, $request->user());

        return redirect()->route('workspaces.index')->with('status', 'Workspace deleted.');
    }
}
