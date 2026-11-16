<?php

namespace Launchpad\Install;

/**
 * Turns modules on or off in an app's config/launchpad.php by changing the `'name' => true,` lines of its `'modules'` block and nothing else, so the
 * comments and every other setting the app has changed stay as they are. A module the file does not list is left alone.
 */
final class ModulePatcher
{
    /** Everything that belongs to a team; the Getting started card counts a team's setup */
    private const TEAMS = ['workspaces', 'billing', 'api', 'webhooks', 'files', 'sso', 'scim', 'sync', 'mcp', 'onboarding'];

    /** The modules each preset turns off */
    public const PRESETS = [
        'full' => null,
        'single-tenant' => self::TEAMS,
        'minimal' => [...self::TEAMS, 'social', 'passkeys', 'login_links', 'notifications', 'site', 'help', 'contact'],
    ];

    /** @return list<string> the modules a preset turns off ('full' none) */
    public static function off(string $preset): array
    {
        return self::PRESETS[$preset] ?? [];
    }

    /** @param array<string, bool> $modules module => on or off */
    public static function patch(string $source, array $modules): string
    {
        if (! preg_match("/'modules' => \[.*?\n    \],/s", $source, $block, PREG_OFFSET_CAPTURE)) {
            return $source;
        }

        $patched = $block[0][0];
        foreach ($modules as $name => $on) {
            $patched = preg_replace("/('".preg_quote($name, '/')."' => )(true|false)(,)/", '${1}'.($on ? 'true' : 'false').'${3}', $patched);
        }

        return substr_replace($source, $patched, $block[0][1], strlen($block[0][0]));
    }

    /** @return list<string> the modules a config file lists */
    public static function listed(string $source): array
    {
        preg_match("/'modules' => \[(.*?)\n    \],/s", $source, $block);
        preg_match_all("/^        '([a-z_]+)' => (?:true|false),/m", $block[1] ?? '', $names);

        return $names[1];
    }
}
