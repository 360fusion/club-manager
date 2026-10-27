<?php

namespace App\Http\Requests\SuperAdminLodgeClaim;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminLodgeClaimController::requestInfo().
 */
class RequestInfoRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => 'required|string|min:3|max:1000',
        ];
    }
}
