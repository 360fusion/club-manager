<?php

namespace App\Http\Requests\Invitation;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to InvitationController::accept().
 */
class AcceptRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => 'required|string|max:255',
        ];
    }
}
