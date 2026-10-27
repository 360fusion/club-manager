<?php

namespace App\Http\Requests\MediaFolder;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MediaFolderController::updateFolder().
 */
class UpdateFolderRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:60',

        ];
    }
}
