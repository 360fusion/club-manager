<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Support\MemberScope;
use Carbon\CarbonImmutable;
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
                'installation' => $club->isInstallationMeeting($meeting),
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
        }));

        // Followed lodges have no member area of their own, so they only join the all-clubs calendar.
        if (! $scope->isSingle()) {
            $items = $items->concat($this->followedLodgeItems($user, $clubIds, $from, $to));
        }

        return $this->flagClashes($items->sortBy(fn (array $item) => $item['start']->getTimestamp())->values());
    }

    /**
     * Expected meetings of the lodges the user follows, worked out from each lodge's pattern.
     * A followed lodge the user already belongs to is left out, because its real meetings are
     * already on the calendar.
     *
     * @param  Collection<int, int>  $memberClubIds
     * @return Collection<int, array<string, mixed>>
     */
    private function followedLodgeItems(User $user, Collection $memberClubIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $lodges = $user->followedLodges()
            ->wherePivot('in_calendar', true)
            ->where('lodges.status', 'active')
            ->where(fn ($lodge) => $lodge->whereNull('lodges.club_id')->orWhereNotIn('lodges.club_id', $memberClubIds->all()))
            ->with(['schedules', 'masonicHall', 'clubType:id,code,name'])
            ->get();

        return $lodges->flatMap(function (Lodge $lodge) use ($from, $to) {
            return $lodge->schedules->flatMap(fn (LodgeSchedule $schedule) => array_map(function (CarbonImmutable $date) use ($lodge, $schedule) {
                $start = $schedule->startsOn($date) ?? $date->startOfDay();

                return [
                    'key' => 'lodge-'.$lodge->id.'-'.$date->toDateString(),
                    'type' => 'lodge',
                    'id' => $lodge->id,
                    'club' => ['id' => null, 'name' => $lodge->displayName(), 'slug' => $lodge->slug, 'colour' => 'slate'],
                    'title' => $lodge->isInstallationOn($date) ? 'Installation meeting (expected)' : 'Meeting (expected)',
                    'installation' => $lodge->isInstallationOn($date),
                    'start' => $start,
                    'end' => $start->addHours(self::MEETING_HOURS),
                    'all_day' => $schedule->start_time === null,
                    'where' => $lodge->masonicHall ? trim($lodge->masonicHall->name.', '.$lodge->masonicHall->fullAddress(), ', ') : null,
                    'reply' => null,
                    'closed' => false,
                    'simple' => false,
                    'url' => route('lodges.show', ['slug' => $lodge->slug], false),
                ];
            }, $schedule->datesBetween($from, $to)));
        })->values();
    }

    /**
     * Mark items that overlap another item, which is most useful across clubs.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function flagClashes(Collection $items): Collection
    {
        // An expected date with no known time cannot honestly clash with anything.
        $timed = $items->reject(fn (array $item) => ! empty($item['all_day']));

        return $items->map(function (array $item) use ($timed) {
            $item['clash'] = empty($item['all_day']) && $timed->contains(fn (array $other) => $other['key'] !== $item['key']
                && $other['start'] < $item['end']
                && $item['start'] < $other['end']);

            return $item;
        });
    }
}
