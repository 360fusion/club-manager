<?php

namespace Launchpad\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Launchpad\Billing\Entitlements;
use Launchpad\Workspaces\CurrentWorkspace;

#[Description('The team this API key belongs to: its name, its address name (slug) and its plan.')]
#[IsReadOnly]
class GetTeam extends Tool
{
    public function handle(Request $request, CurrentWorkspace $current): Response
    {
        $workspace = $current->workspace;

        return Response::json([
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'plan' => config('launchpad.modules.billing') ? app(Entitlements::class)->plan($workspace)->name : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
