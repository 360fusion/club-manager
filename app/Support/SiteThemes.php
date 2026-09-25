<?php

namespace App\Support;

use App\Models\Club;

/**
 * A club's `settings.website_theme` is a layout and a colour scheme combined:
 * `"{layout}:{colorScheme}"`, or the legacy id `masonic` (a fixed design that ignores
 * colour schemes). Keep this in step with resources/js/Support/siteThemes.js.
 */
class SiteThemes
{
    public const LEGACY = 'masonic';

    public const DEFAULT = 'editorial:rust_stone';

    /**
     * @var list<string>
     */
    public const LAYOUTS = ['banded', 'classic', 'editorial', 'bold', 'traditional'];

    /**
     * @var list<string>
     */
    public const COLOR_SCHEMES = ['navy_gold', 'rust_stone', 'crimson_rose', 'violet_coral', 'forest_moss', 'ocean_teal', 'lodge_navy_gold', 'lodge_burgundy_gold'];

    /**
     * A lodge can save up to this many colour schemes of its own (settings.custom_color_schemes).
     */
    public const MAX_CUSTOM_SCHEMES = 2;

    public const CUSTOM_ID_PATTERN = '/^custom-[a-z0-9]{8}$/';

    /**
     * The colour schemes a lodge has made: a list of `{id, name, primary, accent}`.
     *
     * @return list<array{id: string, name: string, primary: string, accent: string}>
     */
    public static function customSchemes(Club $club): array
    {
        $schemes = $club->settings['custom_color_schemes'] ?? [];

        return is_array($schemes) ? array_values(array_filter($schemes, fn ($scheme) => is_array($scheme) && isset($scheme['id'], $scheme['name'], $scheme['primary'], $scheme['accent']))) : [];
    }

    /**
     * Whether `$key` is a theme this club may use: a built-in one, or a layout paired with one of its own colour schemes.
     */
    public static function isValidFor(string $key, Club $club): bool
    {
        if (in_array($key, self::keys(), true)) {
            return true;
        }

        [$layout, $scheme] = array_pad(explode(':', $key, 2), 2, '');

        return in_array($layout, self::LAYOUTS, true)
            && collect(self::customSchemes($club))->contains('id', $scheme);
    }

    /**
     * Every built-in value `website_theme` is allowed to hold (a lodge's own colour schemes are checked with isValidFor()).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [self::LEGACY];

        foreach (self::LAYOUTS as $layout) {
            foreach (self::COLOR_SCHEMES as $scheme) {
                $keys[] = $layout.':'.$scheme;
            }
        }

        return $keys;
    }
}
