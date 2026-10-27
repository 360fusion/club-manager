<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PageAdminController::copyBlock().
 */
class CopyBlockRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The fields of one block; the size of its content is checked in the controller.
            'block' => 'required|array|max:200',
        ];
    }
}
