<?php

namespace Launchpad\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\Membership;
use Launchpad\Workspaces\CurrentWorkspace;

/** The example endpoints: what an API token's workspace is, and who is in it. An app's own controllers read the workspace the same way. */
class WorkspaceApiController extends Controller
{
    public function workspace(CurrentWorkspace $current): JsonResponse
    {
        $workspace = $current->workspace;

        return response()->json(['data' => [
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'plan' => config('launchpad.modules.billing') ? app(Entitlements::class)->plan($workspace)->key : null,
        ]]);
    }

    public function members(CurrentWorkspace $current): JsonResponse
    {
        $members = Membership::where('workspace_id', $current->workspace->id)->with('user')->orderBy('id')->get()
            ->map(fn (Membership $m) => ['name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value]);

        return response()->json(['data' => $members]);
    }
}
