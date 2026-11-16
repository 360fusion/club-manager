<?php

namespace Launchpad\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Launchpad\Metering\Usage;
use Launchpad\Workspaces\CurrentWorkspace;

#[Description('What the team has used of each thing that is counted (this month, or in all, by meter) and the limit its plan sets, if any.')]
#[IsReadOnly]
class GetUsage extends Tool
{
    public function handle(Request $request, CurrentWorkspace $current, Usage $usage): Response
    {
        $meters = [];

        foreach ((array) config('launchpad.metering.meters') as $key => $meter) {
            $meters[] = ['meter' => $key, 'label' => $meter['label'] ?? $key, 'resets' => $meter['resets'] ?? 'monthly',
                'used' => $usage->used($current->workspace, $key), 'limit' => $usage->limit($current->workspace, $key)];
        }

        return Response::json(['meters' => $meters]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function shouldRegister(): bool
    {
        return (array) config('launchpad.metering.meters') !== [];
    }
}
