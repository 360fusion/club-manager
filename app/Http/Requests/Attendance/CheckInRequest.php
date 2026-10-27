<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AttendanceController::checkIn().
 */
class CheckInRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendee_id' => 'required|integer',
            'action' => 'required|in:checkin,undo',

        ];
    }
}
