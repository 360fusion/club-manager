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
            // One person, or everyone in a booking (a scanned ticket).
            'attendee_id' => 'required_without:registration_id|integer',
            'registration_id' => 'required_without:attendee_id|integer',
            'action' => 'required|in:checkin,undo',
        ];
    }
}
