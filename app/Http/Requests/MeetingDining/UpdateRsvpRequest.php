<?php

namespace App\Http\Requests\MeetingDining;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MeetingDiningController::updateRsvp().
 */
class UpdateRsvpRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies',
            'apology_reason' => 'nullable|string|max:1000',
            'dietary_requirements' => 'nullable|string|max:1000',
            'payment_status' => 'nullable|in:unpaid,paid,waived,refunded',
            'payment_reference' => 'nullable|string|max:100',
            'guests' => 'nullable|array|max:50',
            'guests.*.guest_name' => 'nullable|string|max:150',
            'guests.*.dietary_requirements' => 'nullable|string|max:1000',
            'guests.*.attending_dining' => 'nullable|boolean',

        ];
    }
}
