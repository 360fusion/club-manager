<?php

namespace App\Http\Requests\SuperAdminGrandLodge;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminGrandLodgeController::updateGrandLodge().
 */
class UpdateGrandLodgeRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'short_name' => 'nullable|string|max:50',
            'country' => 'required|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',

        ];
    }
}
