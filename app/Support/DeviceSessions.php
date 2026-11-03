<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** The browsers and phones a person is signed in on, read from the database session store (nothing to show with any other driver) */
final class DeviceSessions
{
    /** @return list<array{id: string, device: string, ip: ?string, last_active: string, current: bool}> */
    public static function for(User $user, string $currentId): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)
            ->orderByDesc('last_activity')->limit(20)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'device' => self::describe((string) $s->user_agent),
                'ip' => $s->ip_address,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'current' => $s->id === $currentId,
            ])->all();
    }

    /** Signs one device out. The session making the request is left alone: signing out is the normal logout. */
    public static function revoke(User $user, string $id, string $currentId): bool
    {
        if ($id === $currentId) {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', $id)->delete() > 0;
    }

    // shortcut: a few patterns, not a full user-agent parser; swap in a package if people need more than "Chrome on Mac".
    public static function describe(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'A browser',
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iPhone or iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} on {$system}" : $browser;
    }
}
