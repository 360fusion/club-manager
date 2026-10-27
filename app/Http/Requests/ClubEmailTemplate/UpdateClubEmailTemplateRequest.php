<?php

namespace App\Http\Requests\ClubEmailTemplate;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to ClubEmailTemplateController::update().
 */
class UpdateClubEmailTemplateRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:50000',
        ];
    }
}
