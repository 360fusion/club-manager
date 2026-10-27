<?php

namespace App\Http\Requests\PublicEvent;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PublicEventController::register().
 */
class RegisterRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A hidden field real people leave empty; bots fill it in.
            'website' => 'nullable|string|max:0',
            'contact_name' => 'required|string|max:150',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'attendance_status' => 'nullable|in:attending,tentative',
            'payment_method' => 'nullable|integer|max:4294967295',
            'promo_code' => 'nullable|string|max:40',
            'attendees' => 'required|array|min:1|max:50',
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
