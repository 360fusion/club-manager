<?php

namespace App\Http\Requests\CharityAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to CharityAdminController::updateGrantStatus().
 */
class UpdateGrantStatusRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_status' => 'required|string|max:50',

        ];
    }
}
