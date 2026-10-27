<?php

namespace App\Http\Requests\OfficerRosterAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to OfficerRosterAdminController::updateStatus().
 */
class UpdateStatusRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:draft,proposed,confirmed,installed',

        ];
    }
}
