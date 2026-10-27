<?php

namespace App\Http\Requests\ResetPassword;

use App\Http\Requests\AppRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * The form posted to ResetPasswordController::store().
 */
class StoreResetPasswordRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => ['required', PasswordRule::defaults(), 'confirmed'],
        ];
    }
}
