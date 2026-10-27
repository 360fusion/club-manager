<?php

namespace App\Http\Requests\LodgeVisitor;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to LodgeVisitorController::store().
 */
class StoreLodgeVisitorRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'home_lodge_name' => 'required|string|max:255',
            'home_lodge_number' => 'nullable|string|max:20',
            'rank' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:1000',

        ];
    }
}
