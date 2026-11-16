<?php

namespace Launchpad\Site;

/** The page colours a build sets in config('launchpad.site.theme'), as a safe block of CSS: only --lp-* variables, only plain values. */
final class Theme
{
    /** The stylesheet's address, with its modified time so a browser fetches a changed one at once instead of keeping the old for an hour */
    public static function stylesheet(): string
    {
        // a path on the page's own host: a team's subdomain loads it from itself
        return route('launchpad.css', [], false).'?v='.@filemtime(is_file($own = resource_path('css/vendor/launchpad/launchpad.css')) ? $own : __DIR__.'/../../resources/css/launchpad.css');
    }

    public static function css(): string
    {
        $rules = [];
        $light = self::declarations(array_merge(['--lp-accent' => config('launchpad.site.accent')], (array) config('launchpad.site.theme.light')));

        $light && $rules[] = ":root{{$light}}";

        if ($dark = self::declarations((array) config('launchpad.site.theme.dark'))) {
            $rules[] = "@media (prefers-color-scheme: dark){:root:not([data-theme=\"light\"]){{$dark}}}";
            $rules[] = ":root[data-theme=\"dark\"]{{$dark}}";
        }

        return implode("\n", $rules);
    }

    /** @param  array<string, mixed>  $variables */
    private static function declarations(array $variables): string
    {
        $out = '';

        foreach ($variables as $name => $value) {
            if (is_string($name) && preg_match('/\A--lp-[a-z-]+\z/', $name) && is_scalar($value) && preg_match('/\A[#a-zA-Z0-9(),.%\s"\'-]{1,120}\z/', (string) $value)) {
                $out .= "{$name}:{$value};";
            }
        }

        return $out;
    }
}
