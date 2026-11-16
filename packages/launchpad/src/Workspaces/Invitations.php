<?php

namespace Launchpad\Workspaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Models\CustomRole;
use Launchpad\Models\Invitation;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Notifications\Notice;
use Launchpad\Notifications\WorkspaceInvitationNotification;
use Launchpad\Webhooks\Webhooks;

class Invitations
{
    /** Days an invitation can be accepted */
    public const DAYS = 7;

    /** @throws ValidationException */
    /**
     * @param  array<string, bool>  $permissions  what to tick for the person (keys of config launchpad.workspaces.permissions); only what the inviter holds can be given
     */
    public function invite(Workspace $workspace, string $email, Role $role, Model $by, array $permissions = [], ?CustomRole $custom = null): Invitation
    {
        $member = Membership::where(['workspace_id' => $workspace->id, 'user_id' => $by->getKey()])->first();
        abort_unless($member?->can(Ability::ManageMembers), 403);
        $actor = $member->role;

        $email = AccountRegistration::normalizeEmail($email);

        if (! in_array($role, Role::assignable(), true) || ($role === Role::Admin && $actor !== Role::Owner) || ($actor === Role::Manager && $role !== Role::Member)) {
            throw ValidationException::withMessages(['role' => 'You cannot invite someone with that role.']);
        }

        app(Workspaces::class)->assertCustomRole($workspace, $custom, $role, $member);

        $ticks = [];
        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            if (! array_key_exists($key, $permissions)) {
                continue;
            }

            // nobody hands out more than they have
            if ((bool) $permissions[$key] && ! $member->can($key)) {
                throw ValidationException::withMessages(['permissions' => 'You can only give permissions you have yourself.']);
            }

            $ticks[$key] = (bool) $permissions[$key];
        }

        // an unticked box is not a "no": keep only what adds to the custom role, or its own permissions would be switched off for the person
        if ($custom) {
            $ticks = array_filter($ticks, fn (bool $on, string $key) => $on && ! $custom->grants($key), ARRAY_FILTER_USE_BOTH);
        }

        $existing = AccountRegistration::findByEmail($email);
        if ($existing && Membership::where(['workspace_id' => $workspace->id, 'user_id' => $existing->getKey()])->exists()) {
            throw ValidationException::withMessages(['email' => 'That person is already a member.']);
        }

        $token = Str::random(40);

        $invitation = DB::transaction(function () use ($workspace, $email, $role, $custom, $by, $token, $ticks) {
            // a newer invitation to the same address replaces the older one
            Invitation::where(['workspace_id' => $workspace->id, 'email' => $email])->active()->update(['revoked_at' => now()]);

            return Invitation::create([
                'workspace_id' => $workspace->id,
                'email' => $email,
                'role' => $role,
                'custom_role_id' => $custom?->id,
                'token_hash' => Invitation::hashOf($token),
                'invited_by' => $by->getKey(),
                'permissions' => $ticks ?: null,
                'expires_at' => now()->addDays(self::DAYS),
            ]);
        });

        Notification::route('mail', $email)->notify(new WorkspaceInvitationNotification($invitation, $token));

        return $invitation;
    }

    public function find(string $token): ?Invitation
    {
        return Invitation::active()->where('token_hash', Invitation::hashOf($token))->with('workspace')->first();
    }

    public function revoke(Workspace $workspace, Invitation $invitation, Model $by): void
    {
        $member = Membership::where(['workspace_id' => $workspace->id, 'user_id' => $by->getKey()])->first();
        abort_unless($member?->can(Ability::ManageMembers) && $invitation->workspace_id === $workspace->id, 403);

        $invitation->update(['revoked_at' => now()]);
    }

    /**
     * Joins the person to the workspace. Only an account whose verified address is the one that was invited can accept: a link that
     * reaches somebody else does nothing for them.
     */
    public function accept(Invitation $invitation, Model $user): ?Membership
    {
        if (! $user->hasVerifiedEmail() || AccountRegistration::normalizeEmail($user->email) !== $invitation->email) {
            return null;
        }

        return DB::transaction(function () use ($invitation, $user) {
            $membership = Membership::firstOrCreate(
                ['workspace_id' => $invitation->workspace_id, 'user_id' => $user->getKey()],
                ['role' => $invitation->role, 'custom_role_id' => $invitation->custom_role_id, 'permissions' => $invitation->permissions],
            );

            $invitation->update(['accepted_at' => now()]);
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();

            if ($membership->wasRecentlyCreated) {
                Notice::to($invitation->workspace->owner, 'Someone joined', "{$user->name} joined {$invitation->workspace->name}.", route('workspace.members', $invitation->workspace->slug));
                Webhooks::dispatch($invitation->workspace, 'member.joined', Webhooks::memberPayload($invitation->workspace, $user, $membership->role, $membership->customRole?->name));
            }

            return $membership;
        });
    }

    /** Hands over every invitation waiting for this address (when it has just been verified) */
    public function acceptWaiting(Model $user): int
    {
        $count = 0;

        foreach (Invitation::active()->where('email', AccountRegistration::normalizeEmail($user->email))->get() as $invitation) {
            $count += $this->accept($invitation, $user) ? 1 : 0;
        }

        return $count;
    }
}
