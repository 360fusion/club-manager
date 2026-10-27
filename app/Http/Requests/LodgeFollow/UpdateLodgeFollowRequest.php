<?php

namespace App\Http\Requests\LodgeFollow;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to LodgeFollowController::update().
 */
class UpdateLodgeFollowRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'in_calendar' => 'sometimes|required|boolean', 'notify_summons' => 'sometimes|required|boolean',
        ];
    }
}
