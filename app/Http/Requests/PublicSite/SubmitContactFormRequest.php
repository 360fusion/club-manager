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
            'recipient_email' => 'nullable|email|max:255',
            'cc_emails' => 'nullable|string|max:1000',
            'success_message' => 'nullable|string|max:1000',
        ];
    }
}
