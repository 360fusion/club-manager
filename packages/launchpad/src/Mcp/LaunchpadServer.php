<?php

namespace Launchpad\Mcp;

use Laravel\Mcp\Server;
use Launchpad\Workspaces\CurrentWorkspace;

/**
 * The site's MCP server at /mcp, for a customer's AI assistant: it signs in with one of the team's API keys (Authorization: Bearer), so
 * it sees the one team the key belongs to, with the key's abilities, rate limit, usage log and api_calls meter. An app adds its own tools
 * in config launchpad.mcp.tools; which of them a team gets is McpAccess's call.
 */
class LaunchpadServer extends Server
{
    protected string $version = '1.0.0';

    protected function boot(): void
    {
        $this->name = (string) config('launchpad.site.name', config('app.name'));
        $this->instructions = 'Tools for the one team this API key belongs to on '.$this->name.'. Each answer is about that team only.';
        // what staff left on, and what the team's plan includes
        $this->tools = McpAccess::toolsFor(app(CurrentWorkspace::class)->workspace);
    }
}
