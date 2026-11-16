<?php

namespace Launchpad\Onboarding;

use Closure;
use Illuminate\Support\Facades\Route;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\ApiToken;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceFile;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\WorkspaceScope;

/**
 * The "Getting started" card: a short list of first things to do in a workspace, each ticked off by looking at the workspace itself (nothing is
 * stored but a person's "hide this"). Which built-in steps are listed, in order, is config launchpad.onboarding.steps; an app adds its own with
 * Onboarding::step(). A person is only shown the steps they may do, and no card at all once they are all done or the person has hidden it.
 */
final class Onboarding
{
    /** @var array<string, array{label: string, url: Closure, done: Closure, ability: ?string}> */
    private static array $custom = [];

    /**
     * @param  Closure(Workspace): string  $url  where the person goes to do it
     * @param  Closure(Workspace): bool  $done
     * @param  string|null  $ability  a permission key (Ability or launchpad.workspaces.permissions) the person needs for the step to be theirs
     */
    public static function step(string $key, string $label, Closure $url, Closure $done, ?string $ability = null): void
    {
        self::$custom[$key] = compact('label', 'url', 'done', 'ability');
    }

    public static function flush(): void
    {
        self::$custom = [];
    }

    /** @return array<string, array{label: string, url: Closure, done: Closure, ability: ?string}> */
    private static function builtIn(): array
    {
        return array_filter([
            'invite' => Route::has('workspace.members') ? [
                'label' => __('Invite a teammate'),
                'url' => fn (Workspace $w) => route('workspace.members', $w->slug),
                'done' => fn (Workspace $w) => $w->memberships()->count() > 1 || $w->invitations()->exists(),
                'ability' => Ability::ManageMembers->value,
            ] : null,
            'plan' => Route::has('workspace.billing') ? [
                'label' => __('Choose a plan'),
                'url' => fn (Workspace $w) => route('workspace.billing', $w->slug),
                'done' => fn (Workspace $w) => ! app(Entitlements::class)->plan($w)->isFree(),
                'ability' => Ability::ManageBilling->value,
            ] : null,
            'api' => Route::has('workspace.api') ? [
                'label' => __('Create an API token'),
                'url' => fn (Workspace $w) => route('workspace.api', $w->slug),
                'done' => fn (Workspace $w) => ApiToken::where('workspace_id', $w->id)->exists(),
                'ability' => Ability::ManageWorkspace->value,
            ] : null,
            'files' => Route::has('workspace.files') ? [
                'label' => __('Upload a file'),
                'url' => fn (Workspace $w) => route('workspace.files', $w->slug),
                'done' => fn (Workspace $w) => WorkspaceFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $w->id)->exists(),
                'ability' => null,
            ] : null,
        ]);
    }

    /** @return array{steps: list<array{key: string, label: string, url: string, done: bool}>, done: int, total: int, dismissUrl: string}|null */
    public static function card(Workspace $workspace, Membership $membership): ?array
    {
        if (! config('launchpad.modules.onboarding') || $membership->onboarding_dismissed_at !== null || ! Route::has('workspace.onboarding.dismiss')) {
            return null;
        }

        $builtIn = self::builtIn();
        $steps = [];

        foreach ((array) config('launchpad.onboarding.steps') as $key) {
            isset($builtIn[$key]) && $steps[$key] = $builtIn[$key];
        }

        $steps = array_merge($steps, self::$custom);
        $rows = [];

        foreach ($steps as $key => $step) {
            if ($step['ability'] === null || $membership->can($step['ability'])) {
                $rows[] = ['key' => $key, 'label' => $step['label'], 'url' => ($step['url'])($workspace), 'done' => (bool) ($step['done'])($workspace)];
            }
        }

        $done = count(array_filter(array_column($rows, 'done')));

        if ($rows === [] || $done === count($rows)) {
            return null;
        }

        return ['steps' => $rows, 'done' => $done, 'total' => count($rows), 'dismissUrl' => route('workspace.onboarding.dismiss', $workspace->slug)];
    }

    /** The card for the workspace the person is working in */
    public static function forUser(object $user): ?array
    {
        if (! config('launchpad.modules.onboarding') || ! config('launchpad.modules.workspaces')) {
            return null;
        }

        $workspace = method_exists($user, 'currentWorkspace') ? $user->currentWorkspace() : null;
        // a suspended team has nothing to get started with
        $membership = $workspace && $workspace->suspended_at === null ? Membership::where(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()])->first() : null;

        return $membership ? self::card($workspace, $membership->setRelation('workspace', $workspace)) : null;
    }
}
