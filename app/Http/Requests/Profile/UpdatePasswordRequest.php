<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\AppRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The form posted to ProfileController::updatePassword().
 */
class UpdatePasswordRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
