<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\AppRequest;

/**
 * The form posted to ApiController::issueToken().
 */
class IssueTokenRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'device_name' => 'required|string|max:100',
        ];
    }
}
