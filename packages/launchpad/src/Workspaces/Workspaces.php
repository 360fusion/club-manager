<?php

namespace Launchpad\Workspaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Files\Files;
use Launchpad\Launchpad;
use Launchpad\Models\ApiToken;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Notifications\Notice;
use Launchpad\Webhooks\Webhooks;

/** Making, changing and leaving workspaces. Who may do what is Role::can(); this class checks it, so no controller has to remember to. */
class Workspaces
{
    public function create(Model $owner, string $name, string $kind = 'business'): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $kind) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'kind' => $kind,
                'owner_id' => $owner->getKey(),
                'timezone' => config('app.timezone', 'UTC'),
            ]);

            Membership::create(['workspace_id' => $workspace->id, 'user_id' => $owner->getKey(), 'role' => Role::Owner]);

            if ($owner->current_workspace_id === null) {
                $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            return $workspace;
        });
    }

    public function createPersonal(Model $owner): ?Workspace
    {
        if (Workspace::where(['owner_id' => $owner->getKey(), 'kind' => 'personal'])->exists()) {
            return null;
        }

        return $this->create($owner, 'Personal', 'personal');
    }

    public function membership(Workspace $workspace, Model $user): ?Membership
    {
        return Membership::where(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()])->first();
    }

    /** @throws ValidationException */
    public function changeRole(Workspace $workspace, Membership $target, Role $new, Model $actor): void
    {
        $actorRole = $this->roleOf($workspace, $actor, Ability::ManageMembers);

        // an owner hands out any role but ownership; an admin only the roles below their own
        $allowed = match ($actorRole) {
            Role::Owner => Role::assignable(),
            Role::Admin => [Role::Manager, Role::Member],
            default => [Role::Member],
        };

        if (! $actorRole->outranks($target->role) || ! in_array($new, $allowed, true)) {
            throw ValidationException::withMessages(['role' => 'You cannot give that role to that person.']);
        }

        $target->update(['role' => $new]);
        Notice::to($target->user, 'Your role changed', "You are now {$new->label()} in {$workspace->name}.", route('workspace.members', $workspace->slug));
    }

    /** @throws ValidationException */
    public function remove(Workspace $workspace, Membership $target, Model $actor): void
    {
        $actorRole = $this->roleOf($workspace, $actor, Ability::ManageMembers);

        if (! $actorRole->outranks($target->role)) {
            throw ValidationException::withMessages(['member' => 'You cannot remove that person.']);
        }

        $this->detach($workspace, $target);
    }

    /** @throws ValidationException */
    public function leave(Workspace $workspace, Model $user): void
    {
        $membership = $this->membership($workspace, $user);

        abort_if($membership === null, 404);

        if ($membership->role === Role::Owner) {
            throw ValidationException::withMessages(['member' => 'The owner cannot leave. Transfer ownership first.']);
        }

        $this->detach($workspace, $membership);
    }

    /** @throws ValidationException */
    public function transferOwnership(Workspace $workspace, Membership $to, Model $actor): void
    {
        $this->roleOf($workspace, $actor, Ability::Transfer);

        if ($to->workspace_id !== $workspace->id || $to->user_id === $actor->getKey()) {
            throw ValidationException::withMessages(['member' => 'Choose another member of this workspace.']);
        }

        DB::transaction(function () use ($workspace, $to, $actor) {
            $this->membership($workspace, $actor)->update(['role' => Role::Admin]);
            $to->update(['role' => Role::Owner]);
            $workspace->update(['owner_id' => $to->user_id]);
        });
    }

    /** @throws ValidationException */
    public function delete(Workspace $workspace, Model $actor): void
    {
        $this->roleOf($workspace, $actor, Ability::Delete);

        if ($workspace->isPersonal()) {
            throw ValidationException::withMessages(['workspace' => 'A personal workspace cannot be deleted.']);
        }

        DB::transaction(function () use ($workspace) {
            $userModel = Launchpad::userModel();
            $userModel::where('current_workspace_id', $workspace->id)->update(['current_workspace_id' => null]);
            // removed here as well as by the foreign keys' cascade, so it holds on a database that does not enforce them
            $workspace->memberships()->delete();
            $workspace->invitations()->delete();
            ApiToken::where('workspace_id', $workspace->id)->delete();
            Files::purge($workspace);
            Webhooks::forget($workspace);
            $workspace->delete();
        });
    }

    private function detach(Workspace $workspace, Membership $membership): void
    {
        DB::transaction(function () use ($workspace, $membership) {
            $user = $membership->user;
            $membership->delete();
            $user && Webhooks::dispatch($workspace, 'member.removed', Webhooks::memberPayload($workspace, $user, $membership->role));

            if ($user && $user->current_workspace_id === $workspace->id) {
                $user->forceFill(['current_workspace_id' => Membership::where('user_id', $user->getKey())->value('workspace_id')])->save();
            }
        });
    }

    /** The actor's role in the workspace, or a 403 when they do not have the ability (ticked for them, or given by their role) */
    private function roleOf(Workspace $workspace, Model $actor, Ability $ability): Role
    {
        $membership = $this->membership($workspace, $actor);

        abort_unless($membership?->can($ability), 403);

        return $membership->role;
    }

    /**
     * Sets what is ticked for a person. Whoever does it must be able to manage people and outrank them, and can only change a permission
     * they hold themselves (nobody hands out more than they have). Keys that are not in config launchpad.workspaces.permissions are ignored.
     *
     * @param  array<string, bool>  $ticks
     */
    public function setPermissions(Workspace $workspace, Membership $target, array $ticks, Model $actor): void
    {
        $actorRole = $this->roleOf($workspace, $actor, Ability::ManageMembers);

        if (! $actorRole->outranks($target->role)) {
            throw ValidationException::withMessages(['permissions' => 'You cannot change that person\'s permissions.']);
        }

        $mine = $this->membership($workspace, $actor);
        $kept = (array) $target->permissions;

        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            if (! array_key_exists($key, $ticks)) {
                continue;
            }

            if (! $mine->can($key) && (bool) $ticks[$key] !== $target->can($key)) {
                throw ValidationException::withMessages(['permissions' => 'You can only change permissions you have yourself.']);
            }

            $kept[$key] = (bool) $ticks[$key];
        }

        $target->update(['permissions' => $kept]);
        Notice::to($target->user, 'Your permissions changed', "What you can do in {$workspace->name} was updated.", route('workspace.members', $workspace->slug));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        // a name like www or api would take a subdomain the site needs
        while (TeamAddresses::isReserved($slug) || Workspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
