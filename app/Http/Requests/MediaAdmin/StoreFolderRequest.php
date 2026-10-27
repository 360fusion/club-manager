<?php

namespace App\Http\Requests\MediaAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\MediaFolders;
use Illuminate\Validation\Rule;

/**
 * The form posted to MediaAdminController::storeFolder().
 */
class StoreFolderRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'parent' => ['required', 'string', Rule::in(MediaFolders::NESTABLE)],
        ];
    }
}
