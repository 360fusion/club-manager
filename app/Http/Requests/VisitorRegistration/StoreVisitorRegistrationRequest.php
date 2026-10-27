<?php

namespace App\Http\Requests\VisitorRegistration;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to VisitorRegistrationController::store().
 */
class StoreVisitorRegistrationRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'rank' => 'nullable|string|max:100',
            'home_club_name' => 'required|string|max:255',
            'home_club_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'dietary_notes' => 'nullable|string|max:1000',
        ];
    }
}
