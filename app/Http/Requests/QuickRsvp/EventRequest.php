<?php

namespace App\Http\Requests\QuickRsvp;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to QuickRsvpController::event().
 */
class EventRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_status' => 'required|in:attending,declined,tentative',
        ];
    }
}
