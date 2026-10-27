<?php

namespace App\Http\Requests\UserAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to UserAdminController::updateRank().
 */
class UpdateRankRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rank' => 'nullable|string|max:100',

        ];
    }
}
