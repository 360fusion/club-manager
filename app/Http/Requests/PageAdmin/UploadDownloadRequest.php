<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\UploadRules;

/**
 * The form posted to PageAdminController::uploadDownload().
 */
class UploadDownloadRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:'.UploadRules::DOCUMENT_TYPES.','.UploadRules::IMAGE_TYPES, 'max:10240'],
        ];
    }
}
