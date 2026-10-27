<?php

namespace App\Http\Requests\UserAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to UserAdminController::updateCommitteeRole().
 */
class UpdateCommitteeRoleRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'committee_role' => 'nullable|in:chair,secretary,member',

        ];
    }
}
