<?php

namespace App\Http\Requests\MeetingBusiness;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MeetingBusinessController::storeOfficerElection().
 */
class StoreOfficerElectionRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'masonic_year' => 'required|string|max:20',
            'assignments' => 'present|array|max:100',
            'assignments.*.member_id' => 'required|integer',
            'assignments.*.office' => 'required|string|max:50',
            'notes' => 'nullable|string|max:10000',
            'status' => 'nullable|string|max:50',

        ];
    }
}
