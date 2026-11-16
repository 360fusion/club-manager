<?php

namespace Launchpad\Health;

use Illuminate\Support\Facades\Cache;

/** "Seen in the last five minutes": the scheduler and the queue worker each leave one every minute, so `launchpad:doctor --running` can tell they are alive */
final class Heartbeat
{
    public static function beat(string $what): void
    {
        Cache::put("launchpad.heartbeat.{$what}", true, 300);
    }

    public static function seen(string $what): bool
    {
        return Cache::has("launchpad.heartbeat.{$what}");
    }
}
