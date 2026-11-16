<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Launchpad\Mcp\McpAccess;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/** After launchpad.api: a team whose plan does not include AI access (config launchpad.mcp.feature) is told so */
class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! McpAccess::allowedFor(app(CurrentWorkspace::class)->workspace)) {
            return response()->json(['message' => 'AI access is not part of this team\'s plan.'], 403);
        }

        return $next($request);
    }
}
