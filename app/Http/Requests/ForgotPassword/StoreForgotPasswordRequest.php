<?php

namespace App\Http\Requests\ForgotPassword;

use App\Http\Requests\AppRequest;

/**
 * The form posted to ForgotPasswordController::store().
 */
class StoreForgotPasswordRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
        ];
    }
}
