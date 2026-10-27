<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PageAdminController::storeRedirect().
 */
class StoreRedirectRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_path' => ['required', 'string', 'max:200', 'regex:#^[A-Za-z0-9/_\-.~%]+$#'],
            'to_url' => ['required', 'string', 'max:2048', 'regex:#^(https?://|/[^/])#i'],
            'is_permanent' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_path.regex' => 'Use only letters, numbers and - _ . / in the old address, for example /about-us/history.',
        ];
    }
}
