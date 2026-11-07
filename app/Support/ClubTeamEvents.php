<?php

namespace App\Support;

use Launchpad\Models\Workspace;
use Launchpad\Webhooks\Webhooks;
use Launchpad\Workspaces\Activity;

/**
 * Something happened in a club: one line in its activity log, and one webhook to each of its endpoints that subscribed to the event.
 * The club is a Launchpad team (the same clubs row). Callers are model observers, so each change is recorded once, whoever made it.
 */
final class ClubTeamEvents
{
    /** @param  array<string, mixed>  $payload  ids and the few fields a receiver needs: never bank details, tokens or notes */
    public static function record(int $clubId, string $event, string $summary, array $payload): void
    {
        $team = Workspace::find($clubId);

        if (! $team) {
            return;
        }

        Activity::record($team, $event, $summary);
        Webhooks::dispatch($team, $event, $payload);
    }
}
