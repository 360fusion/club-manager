<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Support\MemberScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Meetings and events across a member's clubs, as one list of dated items.
 */
class MemberCalendar
{
    /**
     * Summonses do not record an end time, so a meeting is shown as this long.
     */
    public const MEETING_HOURS = 3;

    private const EVENT_HOURS = 2;

    /**
     * @return Collection<int, array<string, mixed>> items ordered by start time
     */
    public function items(MemberScope $scope, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $user = $scope->user;
        $clubIds = $scope->clubIds();

        $meetings = Meeting::whereIn('club_id', $clubIds)
            ->where('status', 'published')
            ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $meetingReplies = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('attendance_status', 'meeting_id');

        $events = Event::whereIn('club_id', $clubIds)
            ->published()
            ->visibleTo($user)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $eventReplies = EventRegistration::whereIn('event_id', $events->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('status', 'event_id');

        $items = $meetings->map(function (Meeting $meeting) use ($scope, $meetingReplies) {
            $club = $scope->clubFor($meeting->club_id);
            $start = $meeting->starts_at
                ? $meeting->meeting_date->copy()->setTimeFromTimeString((string) $meeting->starts_at)
                : $meeting->meeting_date->copy()->startOfDay();

            return [
                'key' => 'meeting-'.$meeting->id,
                'type' => 'meeting',
                'id' => $meeting->id,
                'club' => ['id' => $club->id, 'name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()],
                'title' => $meeting->title,
                'start' => $start,
                'end' => $start->copy()->addHours(self::MEETING_HOURS),
                'where' => $meeting->venue,
                'reply' => $meetingReplies[$meeting->id] ?? null,
                'closed' => $meeting->rsvp_cutoff_at ? now()->isAfter($meeting->rsvp_cutoff_at) : false,
                'simple' => true,
                'url' => route('member.meetings.summons', ['slug' => $club->slug, 'id' => $meeting->id], false),
            ];
        })->concat($events->map(function (Event $event) use ($scope, $eventReplies) {
            $club = $scope->clubFor($event->club_id);

            return [
                'key' => 'event-'.$event->id,
                'type' => 'event',
                'id' => $event->id,
                'club' => ['id' => $club->id, 'name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()],
                'title' => $event->title,
                'start' => $event->starts_at,
                'end' => $event->ends_at ?? $event->starts_at->copy()->addHours(self::EVENT_HOURS),
                'where' => $event->location,
                'reply' => $eventReplies[$event->id] ?? null,
                'closed' => (bool) $event->is_booking_closed,
                'simple' => $event->allowsQuickReply(),
                'url' => route('member.events', ['slug' => $club->slug], false),
            ];
        }))->sortBy(fn (array $item) => $item['start']->getTimestamp())->values();

        return $this->flagClashes($items);
    }

    /**
     * Mark items that overlap another item, which is most useful across clubs.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function flagClashes(Collection $items): Collection
    {
        return $items->map(function (array $item) use ($items) {
            $item['clash'] = $items->contains(fn (array $other) => $other['key'] !== $item['key']
                && $other['start'] < $item['end']
                && $item['start'] < $other['end']);

            return $item;
        });
    }
}
