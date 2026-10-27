<?php

namespace App\Http\Requests\MemberRsvp;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MemberRsvpController::updateRsvp().
 */
class UpdateRsvp2Request extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_status' => 'required|in:attending,declined,tentative',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|integer|max:4294967295',
            'promo_code' => 'nullable|string|max:40',
            'attendees' => 'nullable|array|max:50',
            'attendees.*.name' => 'nullable|string|max:150',
            'attendees.*.email' => 'nullable|string|max:255',
            'attendees.*.is_guest' => 'nullable|boolean',
            'attendees.*.ticket_tier_id' => 'nullable|integer',
            'attendees.*.attending_dining' => 'nullable|boolean',
            'attendees.*.starter_item_id' => 'nullable|integer',
            'attendees.*.main_item_id' => 'nullable|integer',
            'attendees.*.dessert_item_id' => 'nullable|integer',
            'attendees.*.dietary_requirements' => 'nullable|string|max:1000',

        ];
    }
}
