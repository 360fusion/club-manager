<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Services\PassedOn\PlatformSummons;

/**
 * Keeps the copies of a lodge's summons passed on to other lodges in step with the meeting (PlatformSummons::follow).
 */
class PassedOnMeetingCopies
{
    public function updated(Meeting $meeting): void
    {
        if ($meeting->wasChanged(['status', 'title', 'meeting_date', 'starts_at', 'venue', 'dress_code', 'festive_board_theme', 'festive_board_menu', 'dining_cost_guest'])) {
            app(PlatformSummons::class)->follow($meeting);
        }
    }

    public function deleting(Meeting $meeting): void
    {
        app(PlatformSummons::class)->follow($meeting, gone: true);
    }
}
