<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PageAdminController::reorder().
 */
class ReorderRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order' => 'required|array|max:500',
            'order.*' => 'integer',
        ];
    }
}
