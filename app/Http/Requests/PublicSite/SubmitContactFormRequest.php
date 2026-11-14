<?php

namespace App\Http\Requests\PublicSite;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PublicSiteController::submitContactForm().
 */
class SubmitContactFormRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:5000',
            // Which contact form element sent it: its recipients are read from the saved page, never from the request.
            'block_id' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
        ];
    }
}
