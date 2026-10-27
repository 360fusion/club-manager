<?php

namespace App\Http\Requests\SuperAdminLodgeCorrection;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminLodgeCorrectionController::reject().
 */
class Reject2Request extends AppRequest
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
