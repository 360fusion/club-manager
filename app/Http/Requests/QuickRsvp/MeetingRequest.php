<?php

namespace App\Http\Requests\QuickRsvp;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to QuickRsvpController::meeting().
 */
class MeetingRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies',
        ];
    }
}
