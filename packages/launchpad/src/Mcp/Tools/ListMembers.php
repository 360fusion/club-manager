<?php

namespace Launchpad\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Launchpad\Models\Membership;
use Launchpad\Workspaces\CurrentWorkspace;

#[Description('The people in the team: name, email address and role (owner, admin, manager or member).')]
#[IsReadOnly]
class ListMembers extends Tool
{
    public function handle(Request $request, CurrentWorkspace $current): Response
    {
        $members = Membership::where('workspace_id', $current->workspace->id)->with('user')->orderBy('id')->get()
            ->map(fn (Membership $m) => ['name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->value]);

        return Response::json(['members' => $members->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
