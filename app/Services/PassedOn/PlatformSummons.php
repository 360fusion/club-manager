<?php

namespace App\Services\PassedOn;

use App\Jobs\SendPassedOnItem;
use App\Models\Club;
use App\Models\Lodge;
use App\Models\Meeting;
use App\Models\PassedOnItem;
use App\Support\VisitorSummons;

/**
 * A lodge on Club Manager that shares its summonses' key facts with anyone publishes its own: when it publishes one,
 * lodges that list it get the key facts without their Secretary passing it on. A lodge that keeps them to its members,
 * or to visitors it approves, would reach hardly anyone that way, so for this it is treated like a lodge not on the
 * platform and Secretaries pass its summonses on by hand.
 */
class PlatformSummons
{
    public static function publishesOwn(Lodge $lodge): bool
    {
        $club = $lodge->isManaged() ? $lodge->club : null;

        return $club !== null && VisitorSummons::visibility($club) === VisitorSummons::PUBLIC_SAFE;
    }

    /**
     * Sends a just-published summons, as its key facts only, to the members of every lodge that lists this one.
     *
     * @return list<int> the users it reached, so its followers are not told a second time
     */
    public function publish(Club $club, Meeting $meeting): array
    {
        $lodge = $club->lodge;
        if (! $lodge || ! self::publishesOwn($lodge) || PassedOnItem::where('meeting_id', $meeting->id)->where('source', PassedOnItem::PLATFORM)->exists()) {
            return [];
        }

        $facts = VisitorSummons::present($meeting, $club);
        $item = PassedOnItem::create([
            'club_id' => $club->id,
            'passed_on_by' => 'Published by '.$club->name,
            'source' => PassedOnItem::PLATFORM,
            'type' => PassedOnItem::SUMMONS,
            'lodge_id' => $lodge->id,
            'meeting_id' => $meeting->id,
            'title' => $facts['title'],
            'meeting_date' => $facts['date'],
            'meeting_time' => $facts['time'],
            'venue' => $facts['venue'],
            'dress_code' => $facts['dress_code'],
            'dedupe_key' => PassedOnItem::dedupeKeyFor(PassedOnItem::SUMMONS, null, $lodge->id, $facts['date'], null, $facts['title'], null),
        ]);

        // Now, not queued: the followers are told next, and must not be told twice.
        SendPassedOnItem::dispatchSync($item->id);

        return $item->deliveries()->whereNotNull('user_id')->pluck('user_id')->all();
    }
}
