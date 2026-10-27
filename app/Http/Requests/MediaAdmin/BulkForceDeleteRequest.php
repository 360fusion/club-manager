<?php

namespace App\Http\Requests\MediaAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MediaAdminController::bulkForceDelete().
 */
class BulkForceDeleteRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
        ];
    }
}
