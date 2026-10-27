<?php

namespace App\Http\Requests\UpdateAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\UploadRules;

/**
 * The form posted to UpdateAdminController::store().
 */
class StoreUpdateRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:summons,provincial,event_notice,general,charity',
            'summary' => 'nullable|string|max:10000',
            'cover_image_url' => 'nullable|string|max:1000',
            'cover_image_file' => UploadRules::image(8192),
            'attachments' => 'nullable|array|max:50',
            'attachment_files.*' => UploadRules::attachment(20480),
            'status' => 'required|string|in:draft,approved,sent,archived',
            'is_important' => 'nullable|boolean',
            'clean_text' => 'nullable|boolean',
        ];
    }
}
