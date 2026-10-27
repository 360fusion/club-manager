<?php

namespace App\Http\Requests\SuperAdminClubType;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminClubTypeController::sendTestEmailTemplate().
 */
class SendTestEmailTemplateRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:200000',

        ];
    }
}
