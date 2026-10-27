<?php

namespace App\Http\Requests\UpdateAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to UpdateAdminController::updateStatus().
 */
class UpdateStatus2Request extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:draft,approved,sent,archived',

        ];
    }
}
