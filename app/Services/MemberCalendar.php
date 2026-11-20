<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Models\User;
use App\Support\MemberScope;
use App\Support\PassedOnPreferences;
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

        $meetings = Meeting::with('club')->whereIn('club_id', $clubIds)
            ->where('status', 'published')
            ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $meetingReplies = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $user->id)
            ->get(['meeting_id', 'attendance_status', 'payment_status', 'said_paid_at'])
            ->keyBy('meeting_id');

        $events = Event::with('club')->whereIn('club_id', $clubIds)
            ->published()
            ->visibleTo($user)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $eventReplies = EventRegistration::whereIn('event_id', $events->pluck('id'))
            ->where('user_id', $user->id)
            ->get(['event_id', 'status', 'total', 'amount_paid', 'payment_status', 'said_paid_at'])
            ->keyBy('event_id');

        $items = $meetings->map(function (Meeting $meeting) use ($scope, $meetingReplies) {
            $club = $scope->clubFor($meeting->club_id);
            $rsvp = $meetingReplies[$meeting->id] ?? null;
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
                'reply' => $rsvp?->attendance_status,
                // The same rule as the inbox's dining payment reminder.
                'unpaid' => $rsvp?->attendance_status === 'attending_dining' && in_array($rsvp->payment_status, [null, 'unpaid'], true) && (float) $meeting->dining_cost_member > 0,
                'said_paid' => $rsvp?->said_paid_at !== null,
                'closed' => $meeting->rsvpClosed(),
                'simple' => true,
                'url' => route('member.meetings.summons', ['slug' => $club->slug, 'id' => $meeting->id], false),
            ];
        })->concat($events->map(function (Event $event) use ($scope, $eventReplies) {
            $club = $scope->clubFor($event->club_id);
            $registration = $eventReplies[$event->id] ?? null;

            return [
                'key' => 'event-'.$event->id,
                'type' => 'event',
                'id' => $event->id,
                'club' => ['id' => $club->id, 'name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()],
                'title' => $event->title,
                'start' => $event->starts_at,
                'end' => $event->ends_at ?? $event->starts_at->copy()->addHours(self::EVENT_HOURS),
                'where' => $event->location,
                'reply' => $registration?->status,
                'unpaid' => $registration?->isGoing() && in_array($registration->statusFromAmounts(), ['unpaid', 'part_paid'], true),
                'said_paid' => $registration?->said_paid_at !== null,
                'closed' => (bool) $event->is_booking_closed,
                'simple' => $event->allowsQuickReply(),
                'url' => route('member.events', ['slug' => $club->slug], false),
            ];
        }));

        // Followed lodges have no member area of their own, so they only join the all-clubs calendar.
        if (! $scope->isSingle()) {
            // A passed-on summons comes last, so it replaces the same lodge's expected date (same key).
            $items = $items->concat($this->followedLodgeItems($user, $clubIds, $from, $to))
                ->concat($this->passedOnItems($user, $from, $to))
                ->keyBy('key')
                ->values();
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
        $muted = PassedOnPreferences::fromArray($user->passed_on_prefs)->mutedLodges();
        $lodges = $user->followedLodges()
            ->wherePivot('in_calendar', true)
            ->whereNotIn('lodges.id', $muted)
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
                    'unpaid' => false,
                    'said_paid' => false,
                    'closed' => false,
                    'simple' => false,
                    'url' => route('lodges.show', ['slug' => $lodge->slug], false),
                ];
            }, $schedule->datesBetween($from, $to)));
        })->values();
    }

    /**
     * Meetings from summonses passed on to the user (PassedOnDelivery), unless they turned that off or muted the lodge.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function passedOnItems(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $prefs = PassedOnPreferences::fromArray($user->passed_on_prefs);
        if (! $prefs->calendar()) {
            return collect();
        }

        return PassedOnDelivery::where('user_id', $user->id)
            ->whereHas('item', fn ($item) => $item->where('type', PassedOnItem::SUMMONS)->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()]))
            ->with('item.lodge.clubType:id,code,name')
            ->get()
            ->reject(fn (PassedOnDelivery $copy) => $prefs->mutes($copy->item->lodge_id))
            ->map(fn (PassedOnDelivery $copy) => $this->passedOnItem($copy))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function passedOnItem(PassedOnDelivery $copy): array
    {
        $item = $copy->item;
        $date = CarbonImmutable::parse($item->meeting_date->toDateString(), LodgeSchedule::TIMEZONE);
        $start = $item->meeting_time ? $date->setTimeFromTimeString($item->meeting_time) : $date->startOfDay();

        return [
            'key' => $item->lodge_id ? 'lodge-'.$item->lodge_id.'-'.$date->toDateString() : 'passed-on-'.$copy->id,
            'type' => 'passed_on',
            'id' => $copy->id,
            'club' => ['id' => null, 'name' => $item->lodge?->displayName() ?? $item->title, 'slug' => $item->lodge?->slug, 'colour' => 'slate'],
            'title' => $item->title,
            'installation' => false,
            'start' => $start,
            'end' => $start->addHours(self::MEETING_HOURS),
            'all_day' => $item->meeting_time === null,
            'where' => $item->venue,
            'reply' => null,
            'unpaid' => false,
            'said_paid' => false,
            'closed' => false,
            'simple' => false,
            'url' => route('members.passed_on.show', ['delivery' => $copy->id], false),
        ];
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
