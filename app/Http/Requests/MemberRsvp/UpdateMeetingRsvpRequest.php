<?php

namespace App\Http\Requests\MemberRsvp;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MemberRsvpController::updateMeetingRsvp().
 */
class UpdateMeetingRsvpRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies',
            'apology_reason' => 'nullable|string|max:1000',
            'dietary_requirements' => 'nullable|string|max:1000',
            'guests' => 'nullable|array|max:'.$club->maxGuestsPerMember(),
            'guests.*.guest_name' => 'nullable|string|max:150',
            'guests.*.dietary_requirements' => 'nullable|string|max:1000',
            'guests.*.attending_dining' => 'nullable|boolean',

        ];
    }
}
