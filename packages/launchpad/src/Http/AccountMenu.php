<?php

namespace Launchpad\Http;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Launchpad\Models\Membership;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\Role;

/** The links of the account area (its side menu) and what the header's user menu offers, for the signed-in person */
final class AccountMenu
{
    /** @return list<array{0: string, 1: string, 2: bool}> label, address, whether it is the page being shown */
    public static function items(object $user): array
    {
        $items = [[__('Profile'), route('account.profile'), request()->routeIs('account.profile')]];
        $items[] = [__('Security'), route('account.security'), request()->routeIs('account.security')];
        if (config('launchpad.modules.notifications')) {
            $items[] = [__('Notifications'), route('notifications.index'), request()->routeIs('notifications.*')];
        }

        $items[] = [__('Email preferences'), route('account.notifications'), request()->routeIs('account.notifications')];
        $items[] = [__('Activity'), route('account.activity'), request()->routeIs('account.activity')];

        $workspace = Route::has('workspace.billing') ? $user->currentWorkspace() : null;

        // the people of the workspace you are working in: who is in it, their roles and permissions, and invitations
        if ($workspace && Route::has('workspace.members')) {
            $items[] = [__('Team'), route('workspace.members', $workspace->slug), request()->routeIs('workspace.members', 'workspace.settings', 'workspaces.*', 'invitations.*')];
        }

        if ($workspace && Route::has('workspace.files')) {
            $items[] = [__('Files'), route('workspace.files', $workspace->slug), request()->routeIs('workspace.files*')];
        }

        if ($workspace) {
            $items[] = [__('Plan and billing'), route('workspace.billing', $workspace->slug), request()->routeIs('workspace.billing*', 'checkout.*')];
        }

        // the workspace's API and webhooks are for whoever may manage it
        $developer = array_filter([Route::has('workspace.api') ? [__('API'), 'workspace.api'] : null, Route::has('workspace.webhooks') ? [__('Webhooks'), 'workspace.webhooks'] : null, Route::has('workspace.activity') ? [__('Team activity'), 'workspace.activity'] : null]);
        $managing = $developer ? ($workspace ?? $user->currentWorkspace()) : null;

        if ($managing && Membership::where(['workspace_id' => $managing->id, 'user_id' => $user->getKey()])->first()?->can(Ability::ManageWorkspace)) {
            foreach ($developer as [$label, $name]) {
                $items[] = [$label, route($name, $managing->slug), request()->routeIs("{$name}*")];
            }
        }

        if ($managing && Route::has('workspace.sso') && Membership::where(['workspace_id' => $managing->id, 'user_id' => $user->getKey()])->value('role') === Role::Owner) {
            $items[] = [__('Single sign-on'), route('workspace.sso', $managing->slug), request()->routeIs('workspace.sso*')];
        }

        $items[] = [__('Data and privacy'), route('account.privacy'), request()->routeIs('account.privacy')];

        return $items;
    }

    /** @return Collection<int, Membership> */
    public static function memberships(object $user)
    {
        return Route::has('workspaces.switch') ? $user->memberships()->with('workspace')->get() : collect();
    }

    public static function adminUrl(object $user): ?string
    {
        return method_exists($user, 'isStaff') && $user->isStaff() && config('launchpad.modules.admin') ? url(config('launchpad.admin.path', 'hq')) : null;
    }
}
