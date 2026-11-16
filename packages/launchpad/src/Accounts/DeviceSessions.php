<?php

namespace Launchpad\Accounts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Launchpad\Support\UserAgent;

/** A person's browsers that are signed in. Only possible while sessions are kept in the database (SESSION_DRIVER=database). */
final class DeviceSessions
{
    public static function available(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'));
    }

    /** @return list<array{device: string, ip: ?string, last_active: Carbon, current: bool}> */
    public static function for(object $user, string $currentId): array
    {
        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->orderByDesc('last_activity')->get()
            ->map(fn ($row) => [
                'device' => UserAgent::describe($row->user_agent),
                'ip' => $row->ip_address,
                'last_active' => Carbon::createFromTimestamp($row->last_activity),
                'current' => $row->id === $currentId,
            ])->all();
    }

    public static function endOthers(object $user, string $currentId): int
    {
        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->where('id', '!=', $currentId)->delete();
    }
}
