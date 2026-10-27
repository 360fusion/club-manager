<?php

namespace App\Http\Requests\Login;

use App\Http\Requests\AppRequest;

/**
 * The form posted to LoginController::store().
 */
class StoreLoginRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
