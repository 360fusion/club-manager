<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Support\ClubTeamEvents;
use Illuminate\Support\Carbon;

/**
 * A meeting scheduled (made, or moved out of draft), changed (title, date, time or venue) or cancelled: in the club's activity log and to
 * its webhooks. A draft is not announced: members cannot see it yet.
 */
class MeetingTeamEvents
{
    public function created(Meeting $meeting): void
    {
        in_array($meeting->status, [null, 'draft'], true) || $this->record($meeting, 'meeting.created', 'scheduled');
    }

    public function updated(Meeting $meeting): void
    {
        if ($meeting->wasChanged('status') && $meeting->status === 'cancelled') {
            $this->record($meeting, 'meeting.cancelled', 'cancelled');
        } elseif ($meeting->wasChanged('status') && $meeting->getOriginal('status') === 'draft') {
            $this->record($meeting, 'meeting.created', 'scheduled');
        } elseif ($meeting->status !== 'draft' && $meeting->wasChanged(['title', 'meeting_date', 'starts_at', 'venue'])) {
            $this->record($meeting, 'meeting.updated', 'changed the date, time or venue of');
        }
    }

    private function record(Meeting $meeting, string $event, string $verb): void
    {
        $date = $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('j M Y') : null;

        ClubTeamEvents::record((int) $meeting->club_id, $event, trim("{$verb} the meeting {$meeting->title}".($date ? " on {$date}" : '')), [
            'meeting_id' => $meeting->id, 'title' => $meeting->title, 'meeting_date' => $date ? Carbon::parse($meeting->meeting_date)->toDateString() : null,
            'starts_at' => $meeting->starts_at, 'venue' => $meeting->venue, 'status' => $meeting->status,
        ]);
    }
}
