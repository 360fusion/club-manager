<?php

namespace App\Services\Notices;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\ClubNotice;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use Illuminate\Support\Collection;

/**
 * Who a notice goes to: every member, the diners or the non-repliers of the lodge's next meeting, or the officers.
 * Always people active in this lodge, never anyone from another.
 */
class NoticeAudience
{
    public function __construct(private MeetingReplies $replies) {}

    /** The lodge's next published meeting, today's included (a notice on the night is the common case). */
    public function nextMeeting(Club $club): ?Meeting
    {
        return Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', today())
            ->orderBy('meeting_date')
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function users(Club $club, string $audience, ?Meeting $meeting): Collection
    {
        $active = $club->users()->wherePivot('status', ClubMemberStatus::Active->value);

        $users = match ($audience) {
            'all' => $active->wherePivot('role', '!=', ClubRole::Visitor->value)->get(),
            'diners' => $meeting ? $active->whereIn('users.id', MeetingRsvp::where('meeting_id', $meeting->id)->where('attendance_status', 'attending_dining')->select('user_id'))->get() : collect(),
            'not_replied' => $meeting ? $this->replies->nonRepliers($meeting) : collect(),
            'officers' => $active->whereIn('users.id', $this->officerUserIds($club))->get(),
            default => collect(),
        };

        return $users->unique('id')->values();
    }

    /**
     * How many people each audience reaches now, for the choices on the page.
     *
     * @return array<string, int>
     */
    public function counts(Club $club, ?Meeting $meeting): array
    {
        return collect(ClubNotice::AUDIENCES)
            ->mapWithKeys(fn (string $audience) => [$audience => $this->users($club, $audience, $meeting)->count()])
            ->all();
    }

    /**
     * Members holding an office (not plain member or committee member) on the latest confirmed roster, or on their own record when there is no roster.
     *
     * @return list<int>
     */
    private function officerUserIds(Club $club): array
    {
        $members = Member::where('club_id', $club->id)->active()->whereNotNull('user_id')->get();
        Member::loadActiveOffices($members);

        $notOffices = [LodgeOffice::Member->value, LodgeOffice::CommitteeMember->value];

        return $members->filter(fn (Member $member) => collect($member->active_offices)
            ->map(fn ($office) => $office instanceof LodgeOffice ? $office->value : $office)
            ->filter(fn ($office) => $office && ! in_array($office, $notOffices, true))->isNotEmpty())
            ->pluck('user_id')->unique()->values()->all();
    }
}
