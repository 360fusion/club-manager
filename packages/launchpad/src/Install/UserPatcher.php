<?php

namespace Launchpad\Install;

/**
 * Adds what Launchpad needs to the app's User model: the traits, and the interfaces Filament asks of a user. It reads Laravel's own User and
 * the usual variations of it; for a file it does not recognise it returns null and the installer prints the lines to add by hand.
 */
final class UserPatcher
{
    public const IMPORTS = [
        'Filament\\Auth\\MultiFactor\\App\\Contracts\\HasAppAuthentication',
        'Filament\\Auth\\MultiFactor\\App\\Contracts\\HasAppAuthenticationRecovery',
        'Filament\\Models\\Contracts\\FilamentUser',
        'Illuminate\\Contracts\\Auth\\MustVerifyEmail',
        'Illuminate\\Contracts\\Translation\\HasLocalePreference',
        'Launchpad\\Accounts\\Concerns\\HasLaunchpadAccount',
        'Launchpad\\Admin\\Concerns\\HasAdminAccess',
        'Launchpad\\Workspaces\\Concerns\\HasWorkspaces',
        'Spatie\\LaravelPasskeys\\Models\\Concerns\\HasPasskeys',
    ];

    public const INTERFACES = ['FilamentUser', 'HasAppAuthentication', 'HasAppAuthenticationRecovery', 'HasLocalePreference', 'HasPasskeys', 'MustVerifyEmail'];

    public const TRAITS = ['HasAdminAccess', 'HasLaunchpadAccount', 'HasWorkspaces'];

    public static function isPatched(string $source): bool
    {
        return str_contains($source, 'HasLaunchpadAccount');
    }

    /** @return string|null the new source, or null if the file is not one this knows how to change */
    public static function patch(string $source): ?string
    {
        if (self::isPatched($source)) {
            return $source;
        }

        if (! preg_match('/^(final\s+)?class\s+\w+\s+extends\s+\w+(\s+implements\s+[^{]+?)?\s*\{/m', $source, $class, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // 1. the class line gets the interfaces it lacks
        $existing = isset($class[2][0]) ? preg_split('/\s*,\s*/', trim(preg_replace('/^\s*implements\s+/', '', $class[2][0]))) : [];
        $all = array_values(array_unique(array_merge($existing, self::INTERFACES)));
        $declaration = preg_replace('/\s+implements\s+[^{]+?(\s*\{)/', '$1', $class[0][0]);
        $declaration = preg_replace('/(\s*)\{$/', ' implements '.implode(', ', $all).'$1{', $declaration);
        $source = substr_replace($source, $declaration, $class[0][1], strlen($class[0][0]));

        // 2. the traits, after the first `use ...;` inside the class (Laravel's `use HasFactory, Notifiable;`), else right after the brace
        $body = strpos($source, '{', $class[0][1]);
        if (preg_match('/^([ \t]+)use [^;]+;\R/m', $source, $use, PREG_OFFSET_CAPTURE, $body)) {
            $at = $use[0][1] + strlen($use[0][0]);
            $source = substr_replace($source, $use[1][0].'use '.implode(', ', self::TRAITS).";\n", $at, 0);
        } else {
            $source = substr_replace($source, "\n    use ".implode(', ', self::TRAITS).";\n", $body + 1, 0);
        }

        // 3. the imports: a commented-out MustVerifyEmail becomes real, the others are added before the class (or its attributes)
        $source = preg_replace('~^//\s*use Illuminate\\\\Contracts\\\\Auth\\\\MustVerifyEmail;\R~m', '', $source);
        $missing = array_filter(self::IMPORTS, fn ($import) => ! preg_match('/^use '.preg_quote($import, '/').';/m', $source));
        $lines = implode("\n", array_map(fn ($import) => "use {$import};", $missing))."\n";

        if (preg_match_all('/^use [^;]+;\R/m', substr($source, 0, (int) strpos($source, 'class ')), $uses, PREG_OFFSET_CAPTURE) && $uses[0]) {
            $last = end($uses[0]);
            $source = substr_replace($source, $lines, $last[1] + strlen($last[0]), 0);
        } else {
            $source = preg_replace('/^(namespace [^;]+;\R)/m', "$1\n".addcslashes($lines, '\\$'), $source, 1);
        }

        return $source;
    }
}
