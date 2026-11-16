<?php

namespace Launchpad\Mcp;

use Laravel\Mcp\Server\Tool;
use Launchpad\Billing\Entitlements;
use Launchpad\Mcp\Tools\GetTeam;
use Launchpad\Mcp\Tools\GetUsage;
use Launchpad\Mcp\Tools\ListMembers;
use Launchpad\Models\Setting;
use Launchpad\Models\Workspace;

/**
 * What a team's AI assistant may use through /mcp. The server as a whole can need a plan feature (config launchpad.mcp.feature), and so can a
 * single tool (launchpad.mcp.tool_features, by tool name); staff switch any tool off for everyone on HQ's AI access page (Setting mcp_tools_off).
 */
final class McpAccess
{
    /** @return list<class-string<Tool>> the package's tools and the app's (config launchpad.mcp.tools) */
    public static function tools(): array
    {
        return [GetTeam::class, ListMembers::class, GetUsage::class, ...array_values((array) config('launchpad.mcp.tools', []))];
    }

    public static function name(string $tool): string
    {
        return app($tool)->name();
    }

    /** @return list<string> tool names staff switched off */
    public static function off(): array
    {
        return array_values((array) Setting::get('mcp_tools_off', []));
    }

    public static function featureOf(string $name): ?string
    {
        return ((array) config('launchpad.mcp.tool_features', []))[$name] ?? null;
    }

    /** Whether the team's plan includes AI access at all */
    public static function allowedFor(Workspace $workspace): bool
    {
        $feature = config('launchpad.mcp.feature');

        return ! config('launchpad.modules.billing') || blank($feature) || app(Entitlements::class)->allows($workspace, $feature);
    }

    /** @return list<class-string<Tool>> the tools this team's assistant sees */
    public static function toolsFor(Workspace $workspace): array
    {
        $off = self::off();

        return array_values(array_filter(self::tools(), function (string $tool) use ($off, $workspace) {
            $name = self::name($tool);
            $feature = self::featureOf($name);

            return ! in_array($name, $off, true)
                && (! $feature || ! config('launchpad.modules.billing') || app(Entitlements::class)->allows($workspace, $feature));
        }));
    }
}
