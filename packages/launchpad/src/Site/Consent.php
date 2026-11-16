<?php

namespace Launchpad\Site;

use Illuminate\Http\Request;

/**
 * The visitor's cookie choice, in a cookie of our own (`lp_consent`): "necessary" or a comma list of the other kinds they allowed
 * ("analytics,marketing"). Nothing that is not strictly necessary may load before they have chosen: wrap it in @consent('analytics').
 */
final class Consent
{
    public const COOKIE = 'lp_consent';

    public const CATEGORIES = ['analytics', 'marketing'];

    /** The words a person sees for a kind of cookie, as whole translatable sentences */
    public static function label(string $category): string
    {
        return match ($category) {
            'analytics' => __('Analytics cookies'),
            'marketing' => __('Marketing cookies'),
            default => __(':category cookies', ['category' => ucfirst($category)]),
        };
    }

    /** Whether the visitor has chosen at all (the banner is shown until they do) */
    public static function decided(?Request $request = null): bool
    {
        return ($request ?? request())->cookie(self::COOKIE) !== null;
    }

    public static function granted(string $category, ?Request $request = null): bool
    {
        $value = (string) ($request ?? request())->cookie(self::COOKIE);

        return in_array($category, array_filter(explode(',', $value)), true);
    }

    /** @param  list<string>  $categories */
    public static function value(array $categories): string
    {
        $allowed = array_values(array_intersect($categories, self::CATEGORIES));

        return $allowed === [] ? 'necessary' : implode(',', $allowed);
    }
}
