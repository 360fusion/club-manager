<?php

namespace App\Http\Requests\MediaAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MediaAdminController::update().
 */
class UpdateMediaRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',

        ];
    }
}
