<?php

namespace App\Http\Requests\Register;

use App\Http\Requests\AppRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The form posted to RegisterController::store().
 */
class StoreRegisterRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
