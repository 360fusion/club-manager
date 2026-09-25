<?php

namespace App\Support;

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
    public const LAYOUTS = ['banded', 'classic', 'editorial', 'bold'];

    /**
     * @var list<string>
     */
    public const COLOR_SCHEMES = ['navy_gold', 'rust_stone', 'crimson_rose', 'violet_coral', 'forest_moss', 'ocean_teal'];

    /**
     * Every value `website_theme` is allowed to hold.
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
