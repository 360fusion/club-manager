<?php

namespace App\Http\Requests\MeetingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MeetingAdminController::generateSeason().
 */
class GenerateSeasonRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2025|max:2035',
            'occurrence' => 'required|in:1st,2nd,3rd,4th,last',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'starts_at' => 'nullable|string|max:20',
            'rehearsal_starts_at' => 'nullable|string|max:20',
            'active_months' => 'required|array|min:1|max:100',
        ];
    }
}
