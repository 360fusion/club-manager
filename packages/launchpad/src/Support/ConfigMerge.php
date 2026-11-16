<?php

namespace Launchpad\Support;

/**
 * How the package's config and the app's meet. Laravel's own mergeConfigFrom is one level deep: an app that published the file and set a few
 * values under `site` would lose every key a later version of Launchpad adds there. Here a map merges key by key (the app's value wins), and a
 * list, or a map named in $atomic, is the app's whole or the package's whole.
 */
final class ConfigMerge
{
    /** Maps that are one value: an app that lists its own plans means exactly those plans */
    public const ATOMIC = ['billing.plans'];

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $app
     * @param  list<string>  $atomic
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $app, array $atomic = self::ATOMIC, string $path = ''): array
    {
        $merged = $defaults;

        foreach ($app as $key => $value) {
            $at = $path === '' ? (string) $key : "{$path}.{$key}";

            $bothMaps = is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])
                && ! array_is_list($value) && ! array_is_list($defaults[$key]) && ! in_array($at, $atomic, true);

            $merged[$key] = $bothMaps ? self::merge($defaults[$key], $value, $atomic, $at) : $value;
        }

        return $merged;
    }
}
