<?php

namespace App\Http\Requests\LodgeClaim;

use App\Http\Requests\AppRequest;

/**
 * The form posted to LodgeClaimController::respond().
 */
class RespondRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reply' => 'required|string|min:2|max:2000',
        ];
    }
}
