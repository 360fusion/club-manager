<?php

namespace App\Http\Requests\PlatformPaymentsAdmin;

use App\Http\Requests\AppRequest;

/**
 * The form posted to PlatformPaymentsAdminController::update().
 */
class UpdatePlatformPaymentsRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'commission_fixed' => 'nullable|numeric|min:0|max:1000',

        ];
    }
}
