<?php

namespace Launchpad\Support;

/** "Chrome on macOS" from a user agent string: enough to recognise your own devices, not a parser */
final class UserAgent
{
    public static function describe(?string $agent): string
    {
        if (blank($agent)) {
            return __('Unknown device');
        }

        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'])->first(fn ($name, $needle) => str_contains($agent, $needle), __('Browser'));
        $system = collect(['iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'])->first(fn ($name, $needle) => str_contains($agent, $needle));

        return $system ? __(':browser on :system', ['browser' => $browser, 'system' => $system]) : $browser;
    }
}
