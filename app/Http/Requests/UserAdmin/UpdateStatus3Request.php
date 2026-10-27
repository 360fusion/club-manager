<?php

namespace App\Http\Requests\UserAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to UserAdminController::updateStatus().
 */
class UpdateStatus3Request extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:active,inactive,past,pending,deleted',

        ];
    }
}
