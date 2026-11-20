<?php

namespace App\Services\Meetings;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Models\MeetingWalkIn;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Who actually came to a meeting, ticked off at the door: every member (whatever they replied), the guests members said
 * they would bring, registered visitors who replied, and visitors who walk in. From it comes each brother's attendance
 * history and the list of those not seen lately, for the Almoner.
 */
class SigningInBook
{
    public function __construct(private MeetingReplies $replies) {}

    /**
     * @return array{members: list<array<string, mixed>>, guests: list<array<string, mixed>>, walk_ins: list<array<string, mixed>>, counts: array{members: int, guests: int, visitors: int, total: int}}
     */
    public function book(Meeting $meeting): array
    {
        $club = $meeting->club;
        $rsvps = MeetingRsvp::where('meeting_id', $meeting->id)->with(['user', 'guests'])->get()->keyBy('user_id');
        $people = $club->users()->wherePivot('status', ClubMemberStatus::Active->value)->get()->unique('id');
        $name = MeetingReplies::rankedNamer($club->id, $people->pluck('id'));

        // Members always; a registered visitor only once they have said they are coming, or been signed in.
        $members = $people
            ->filter(fn (User $user) => $user->pivot->role !== ClubRole::Visitor->value || in_array($rsvps->get($user->id)?->attendance_status, MeetingReplies::ATTENDING, true) || $rsvps->get($user->id)?->attended_at)
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'name' => $name($user),
                'visitor' => $user->pivot->role === ClubRole::Visitor->value,
                'reply' => MeetingReplies::label($rsvps->get($user->id)?->attendance_status),
                'signed_in' => $rsvps->get($user->id)?->attended_at !== null,
            ])
            ->sortBy(fn (array $person) => [$person['visitor'] ? 1 : 0, mb_strtolower(preg_replace('/^\S+\s+/', '', $person['name']) ?? $person['name'])])
            ->values();

        $guests = $rsvps
            ->flatMap(fn (MeetingRsvp $rsvp) => $rsvp->guests
                ->filter(fn (MeetingRsvpGuest $guest) => $guest->attended_at || (in_array($rsvp->attendance_status, MeetingReplies::ATTENDING, true) && ($guest->attending_dining || ! in_array($guest->payment_status, ['paid', 'refunded'], true))))
                ->map(fn (MeetingRsvpGuest $guest) => [
                    'id' => $guest->id,
                    'name' => trim(($guest->guest_title_rank ? $guest->guest_title_rank.' ' : '').$guest->guest_name),
                    'host' => $name($rsvp->user),
                    'lodge' => $guest->home_club_lodge,
                    'signed_in' => $guest->attended_at !== null,
                ]))
            ->sortBy('name')
            ->values();

        $walkIns = MeetingWalkIn::where('meeting_id', $meeting->id)->orderBy('created_at')->get()
            ->map(fn (MeetingWalkIn $walkIn) => ['id' => $walkIn->id, 'name' => trim($walkIn->rank.' '.$walkIn->name), 'lodge' => $walkIn->lodge]);

        $membersIn = $members->where('signed_in', true)->where('visitor', false)->count();
        $visitorsIn = $members->where('signed_in', true)->where('visitor', true)->count() + $walkIns->count();
        $guestsIn = $guests->where('signed_in', true)->count();

        return [
            'members' => $members->all(),
            'guests' => $guests->all(),
            'walk_ins' => $walkIns->all(),
            'counts' => ['members' => $membersIn, 'guests' => $guestsIn, 'visitors' => $visitorsIn, 'total' => $membersIn + $guestsIn + $visitorsIn],
        ];
    }

    /**
     * Sign a member (or registered visitor) in or out. Someone who never replied gets a reply row to hold it.
     */
    public function setMember(Meeting $meeting, User $person, bool $present, User $by): void
    {
        $this->replies->rsvpFor($meeting, $person)->forceFill([
            'attended_at' => $present ? now() : null,
            'attendance_recorded_by' => $present ? $by->id : null,
        ])->save();
    }

    public function setGuest(MeetingRsvpGuest $guest, bool $present): void
    {
        $guest->forceFill(['attended_at' => $present ? now() : null])->save();
    }

    /**
     * Whether the book was used for a meeting: only those meetings count towards attendance, so a lodge that does not
     * sign people in is never told everyone is missing.
     */
    public static function wasUsed(Meeting $meeting): bool
    {
        return MeetingRsvp::where('meeting_id', $meeting->id)->whereNotNull('attended_at')->exists()
            || MeetingWalkIn::where('meeting_id', $meeting->id)->exists();
    }

    /**
     * Each member's attendance at the meetings since a date (up to today, or `$until`) where the book was used, most
     * absent first, with how many of the latest meetings in a row they have missed.
     *
     * @return array{meetings: int, members: list<array{user_id: int, name: string, email: ?string, phone: ?string, attended: int, last_attended: ?string, missed_in_a_row: int}>}
     */
    public function attendance(Club $club, CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $meetings = Meeting::where('club_id', $club->id)
            ->whereBetween('meeting_date', [$since->toDateString(), ($until ?? now())->toDateString()])
            ->orderByDesc('meeting_date')
            ->get()
            ->filter(fn (Meeting $meeting) => self::wasUsed($meeting))
            ->values();

        $present = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))->whereNotNull('attended_at')->get(['meeting_id', 'user_id'])
            ->groupBy('user_id')->map(fn (Collection $rows) => $rows->pluck('meeting_id')->all());

        $members = $club->users()
            ->wherePivot('status', ClubMemberStatus::Active->value)
            ->wherePivot('role', '!=', ClubRole::Visitor->value)
            ->get()
            ->unique('id');
        $name = MeetingReplies::rankedNamer($club->id, $members->pluck('id'));

        $rows = $members->map(function (User $user) use ($meetings, $present, $name) {
            $came = $present->get($user->id, []);
            $missed = 0;
            foreach ($meetings as $meeting) {
                if (in_array($meeting->id, $came, true)) {
                    break;
                }
                $missed++;
            }

            return [
                'user_id' => $user->id,
                'name' => $name($user),
                'email' => $user->email,
                'phone' => $user->pivot->phone ?? null,
                'attended' => count($came),
                'last_attended' => $meetings->first(fn (Meeting $meeting) => in_array($meeting->id, $came, true))?->meeting_date->format('j M Y'),
                'missed_in_a_row' => $missed,
            ];
        })->sortBy([['missed_in_a_row', 'desc'], ['name', 'asc']])->values();

        return ['meetings' => $meetings->count(), 'members' => $rows->all()];
    }
}
