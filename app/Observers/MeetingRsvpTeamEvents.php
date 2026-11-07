<?php

namespace App\Observers;

use App\Models\MeetingRsvp;
use App\Support\ClubTeamEvents;

/**
 * A member's reply to a meeting. A summons makes a pending row for each member (RsvpTokenService), which is not a reply, so only setting
 * the answer to something else is recorded. Payment changes are not.
 */
class MeetingRsvpTeamEvents
{
    private const ANSWERS = [
        'attending_dining' => 'is coming and dining',
        'attending_meeting_only' => 'is coming to the meeting only',
        'apologies' => 'sent apologies',
    ];

    public function created(MeetingRsvp $rsvp): void
    {
        $this->record($rsvp);
    }

    public function updated(MeetingRsvp $rsvp): void
    {
        // not wasRecentlyCreated: it stays true for every later save of a row made in the same request
        $rsvp->wasChanged('attendance_status') && $this->record($rsvp);
    }

    private function record(MeetingRsvp $rsvp): void
    {
        $answer = $rsvp->attendance_status;

        if (in_array($answer, [null, 'pending'], true)) {
            return;
        }

        $meeting = $rsvp->meeting;

        if (! $meeting) {
            return;
        }

        $name = $rsvp->user?->name ?? 'A member';

        ClubTeamEvents::record((int) $meeting->club_id, 'meeting.rsvp', "recorded {$name}'s reply to the meeting {$meeting->title}: ".(self::ANSWERS[$answer] ?? $answer), [
            'meeting_id' => $meeting->id, 'user_id' => $rsvp->user_id, 'attendance_status' => $answer,
        ]);
    }
}
