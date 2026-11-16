<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/**
 * `workspace` or `workspace:manage-members`. Finds the workspace the route's {slug} names among the signed-in person's own: one they are
 * not in is a 404, the same as one that does not exist, so a slug tells nobody which workspaces are there. With an ability named, a member
 * who lacks it (by role or by what was ticked for them) gets the no-permission page, a 403.
 */
class ResolveWorkspace
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $slug = $request->route('slug');
        $membership = $slug && $request->user()
            ? Membership::query()->where('user_id', $request->user()->getKey())
                ->whereHas('workspace', fn ($query) => $query->where('slug', $slug))
                ->with('workspace')->first()
            : null;

        abort_if($membership === null, 404);

        $current = app(CurrentWorkspace::class);
        $current->set($membership->workspace, $membership);

        // the billing pages stay open, so an owner can pay what the suspension may be about
        if ($membership->workspace->suspended_at !== null && ! str_starts_with((string) $request->route()?->getName(), 'workspace.billing')) {
            return $this->suspended($request, $membership);
        }

        if ($ability !== null && ! $current->can(Ability::tryFrom($ability) ?? $ability)) {
            return $this->noPermission($request, $membership, $ability);
        }

        return $next($request);
    }

    private function suspended(Request $request, Membership $membership): Response
    {
        $billingUrl = $membership->can(Ability::ManageBilling) && app('router')->has('workspace.billing') ? route('workspace.billing', ['slug' => $membership->workspace->slug]) : null;
        $page = Ui::render('workspaces.suspended', ['workspace' => $membership->workspace, 'billingUrl' => $billingUrl],
            fn () => ['workspace' => ['name' => $membership->workspace->name, 'slug' => $membership->workspace->slug], 'billingUrl' => $billingUrl]);

        return $page instanceof View ? response($page->render(), 403) : $page->toResponse($request)->setStatusCode(403);
    }

    /**
     * The page a person sees in the app's own layout when they are in someone else's workspace (or hold a lesser role) and the area is not
     * theirs: what they are in, what they lack, and a link to the same area in a workspace of their own where they can.
     */
    private function noPermission(Request $request, Membership $membership, string $ability): Response
    {
        $own = Membership::where('user_id', $membership->user_id)->with('workspace')->get()
            ->first(fn (Membership $other) => $other->workspace_id !== $membership->workspace_id && $other->can(Ability::tryFrom($ability) ?? $ability));
        $route = $request->route();
        $ownUrl = $own && $request->isMethod('GET') && $route->getName() ? route($route->getName(), ['slug' => $own->workspace->slug] + $route->parameters()) : null;

        // the label is the app's own config text, wrapped here where it is shown
        $what = config("launchpad.workspaces.permissions.{$ability}.0") ? mb_strtolower(__(config("launchpad.workspaces.permissions.{$ability}.0"))) : str_replace('-', ' ', $ability);
        $ownerName = $membership->workspace->owner?->name;

        $page = Ui::render('workspaces.no-permission', [
            'workspace' => $membership->workspace,
            'role' => $membership->role,
            'ownerName' => $ownerName,
            'what' => $what,
            'ownUrl' => $ownUrl,
            'ownName' => $own?->workspace->name,
        ], fn () => [
            'workspace' => ['name' => $membership->workspace->name, 'slug' => $membership->workspace->slug],
            'role' => $membership->role->value,
            'ownerName' => $ownerName,
            'what' => $what,
            'ownUrl' => $ownUrl,
            'ownName' => $own?->workspace->name,
        ]);

        // still a 403 (for a Blade page and for an Inertia one)
        return $page instanceof View ? response($page->render(), 403) : $page->toResponse($request)->setStatusCode(403);
    }
}
