<?php

namespace Launchpad\Models;

/** The words for action codes: the package's, and the ones an app has added */
final class AdminLogLabels
{
    /** @var array<string, string> */
    public static array $extra = [];

    public static function for(string $action): string
    {
        return self::$extra[$action] ?? AdminAction::ACTIONS[$action] ?? $action;
    }
}
