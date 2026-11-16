<?php

namespace Launchpad\Accounts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Admin\AdminLog;
use Launchpad\Jobs\CancelWorkspaceSubscriptions;
use Launchpad\Launchpad;
use Launchpad\Models\AccountEvent;
use Launchpad\Models\ApiToken;
use Launchpad\Models\Invitation;
use Launchpad\Models\Membership;
use Launchpad\Models\SocialAccount;
use Launchpad\Models\Workspace;
use Launchpad\Webhooks\Webhooks;

/**
 * Closing an account: it stops working at once, what it pays for is ended, and it is kept for `launchpad.accounts.retention_days` (30) so that
 * a mistake can be undone. After that `launchpad:purge-closed` removes it for good.
 */
class AccountClosure
{
    public function retentionDays(): int
    {
        return (int) config('launchpad.accounts.retention_days', 30);
    }

    /**
     * @throws ValidationException when the account owns a workspace that other people use: ownership must be handed over first
     */
    public function close(Model $user, ?Model $by = null): void
    {
        if ($user->isClosed()) {
            return;
        }

        $owned = Schema::hasTable((new Workspace)->getTable()) ? Workspace::where('owner_id', $user->getKey())->get() : collect();
        $shared = $owned->first(fn (Workspace $workspace) => $workspace->memberships()->where('user_id', '!=', $user->getKey())->exists());

        if ($shared) {
            throw ValidationException::withMessages(['account' => __(':team has other members. Transfer its ownership, or remove them, before closing this account.', ['team' => $shared->name])]);
        }

        DB::transaction(function () use ($user) {
            // a password nobody knows and no remember cookie; the sessions in the database go too
            $user->forceFill(['deletion_pending_at' => now(), 'remember_token' => Str::random(60)])->save();

            if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            }
        });

        foreach ($owned as $workspace) {
            CancelWorkspaceSubscriptions::dispatch($workspace);
        }

        AdminLog::record('account.closed', $user, "Closed {$user->email}", actor: $by);
    }

    /** Puts a closed account back. What it paid for was ended and is not restarted: it buys again. */
    public function restore(Model $user, ?Model $by = null): void
    {
        if (! $user->isClosed()) {
            return;
        }

        $user->forceFill(['deletion_pending_at' => null])->save();

        AdminLog::record('account.restored', $user, "Restored {$user->email}", actor: $by);
    }

    /** Removes a closed account for good: the app's own data first (AccountPurging), then the workspaces it owned, its memberships and the account */
    public function purge(Model $user): void
    {
        abort_unless($user->isClosed(), 409, 'Only a closed account can be removed.');

        $email = $user->email;

        DB::transaction(function () use ($user) {
            event(new AccountPurging($user));

            // an app without teams, the API or social sign-in has none of these tables (Modules)
            foreach (Schema::hasTable((new Workspace)->getTable()) ? Workspace::where('owner_id', $user->getKey())->get() : [] as $workspace) {
                Membership::where('workspace_id', $workspace->id)->delete();
                Invitation::where('workspace_id', $workspace->id)->delete();
                Schema::hasTable('personal_access_tokens') && ApiToken::where('workspace_id', $workspace->id)->delete();
                Schema::hasTable('webhook_endpoints') && Webhooks::forget($workspace);
                Launchpad::userModel()::where('current_workspace_id', $workspace->id)->update(['current_workspace_id' => null]);
                $workspace->delete();
            }

            Schema::hasTable('memberships') && Membership::where('user_id', $user->getKey())->delete();
            Schema::hasTable('social_accounts') && SocialAccount::where('user_id', $user->getKey())->delete();
            method_exists($user, 'tokens') && Schema::hasTable('personal_access_tokens') && $user->tokens()->delete();
            AccountEvent::where('user_id', $user->getKey())->delete();
            Avatar::remove($user);
            Schema::hasTable('invitations') && Invitation::where('invited_by', $user->getKey())->update(['invited_by' => null]);
            $user->delete();
        });

        AdminLog::record('account.purged', null, "Removed {$email}");
    }

    /** The closed accounts that have been closed for longer than the retention period */
    public function due(): Collection
    {
        return Launchpad::userModel()::whereNotNull('deletion_pending_at')
            ->where('deletion_pending_at', '<=', now()->subDays($this->retentionDays()))->get();
    }
}
