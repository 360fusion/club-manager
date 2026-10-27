<?php

namespace App\Http\Requests\SuperAdminLodgeClaim;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminLodgeClaimController::reject().
 */
class RejectRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
        ];
    }
}
