<?php

namespace App\Services\PassedOn;

use App\Jobs\SendPassedOnItem;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\Lodge;
use App\Models\Meeting;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Notifications\ClubNotification;
use App\Support\VisitorSummons;
use Illuminate\Support\Facades\Mail;

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

        $item = PassedOnItem::create([
            'club_id' => $club->id,
            'passed_on_by' => 'Published by '.$club->name,
            'source' => PassedOnItem::PLATFORM,
            'type' => PassedOnItem::SUMMONS,
            'lodge_id' => $lodge->id,
            'meeting_id' => $meeting->id,
            ...$this->facts($meeting, $club),
        ]);

        // Now, not queued: the followers are told next, and must not be told twice.
        SendPassedOnItem::dispatchSync($item->id);

        return $item->deliveries()->whereNotNull('user_id')->pluck('user_id')->all();
    }

    /**
     * The lodge changed or cancelled (or deleted) a meeting whose summons went to other lodges: the copy follows it, and
     * those who had it are told when the date, time or venue changed, or it was cancelled. Called by PassedOnMeetingCopies.
     */
    public function follow(Meeting $meeting, bool $gone = false): void
    {
        $item = PassedOnItem::where('meeting_id', $meeting->id)->where('source', PassedOnItem::PLATFORM)->first();
        $club = $meeting->club;
        if (! $item || ! $club) {
            return;
        }

        if ($gone || $meeting->status === 'cancelled') {
            if ($item->cancelled_at === null) {
                $item->update(['cancelled_at' => now()]);
                $this->tell($item, 'Cancelled: '.$item->title, 'The meeting on '.$item->meeting_date->format('j M Y').' is cancelled.');
            }

            return;
        }

        $before = [$item->meeting_date->toDateString(), $item->meeting_time, $item->venue];
        $item->update([...$this->facts($meeting, $club), 'cancelled_at' => null]);
        PassedOnDelivery::where('item_id', $item->id)->update(['dedupe_key' => $item->dedupe_key]);

        if ($before !== [$item->meeting_date->toDateString(), $item->meeting_time, $item->venue]) {
            $this->tell($item, 'Changed: '.$item->title, trim($item->meeting_date->format('l j F Y').($item->meeting_time ? ', '.$item->meeting_time : '').($item->venue ? ' at '.$item->venue : '')).'.');
        }
    }

    /**
     * What is shared about the meeting: the key facts visitors see, including dining.
     *
     * @return array<string, mixed>
     */
    private function facts(Meeting $meeting, Club $club): array
    {
        $facts = VisitorSummons::present($meeting, $club);
        $dining = array_filter($facts['festive_board'], fn ($value) => filled($value));

        return [
            'title' => $facts['title'],
            'meeting_date' => $facts['date'],
            'meeting_time' => $facts['time'],
            'venue' => $facts['venue'],
            'dress_code' => $facts['dress_code'],
            'dining' => $dining === [] ? null : $dining,
            'dedupe_key' => PassedOnItem::dedupeKeyFor(PassedOnItem::SUMMONS, null, $club->lodge?->id, $facts['date'], null, $facts['title'], null),
        ];
    }

    /** Each person as they had it: in the app, by email, or both. */
    private function tell(PassedOnItem $item, string $title, string $body): void
    {
        $item->deliveries()->with(['user', 'viaClub'])->get()->each(function (PassedOnDelivery $copy) use ($item, $title, $body) {
            $copy->setRelation('item', $item);
            if ($copy->bell && $copy->user && $copy->viaClub) {
                $copy->user->notifyNow(ClubNotification::passedOnChanged($copy, $title, $body));
            }
            if ($copy->emailed && $copy->email) {
                Mail::to($copy->email)->send(new LodgeClaimMail($title, $body, 'Open it', route('passed_on.public', ['token' => $copy->token])));
            }
        });
    }
}
