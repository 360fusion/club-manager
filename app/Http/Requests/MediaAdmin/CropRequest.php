<?php

namespace App\Http\Requests\MediaAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\UploadRules;

/**
 * The form posted to MediaAdminController::crop().
 */
class CropRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => UploadRules::image(10240, required: true),
            'save_mode' => 'nullable|string|in:replace,variant',

        ];
    }
}
