<?php

namespace App\Http\Requests\TwoFactorAuth;

use App\Http\Requests\AppRequest;

/**
 * The form posted to TwoFactorAuthController::confirm().
 */
class ConfirmRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:32',
        ];
    }
}
