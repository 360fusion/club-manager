<?php

namespace App\Http\Requests\SuperAdminGrandLodge;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminGrandLodgeController::updateGrandLodgeRanks().
 */
class UpdateGrandLodgeRanksRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grand_ranks' => 'nullable|array|max:200',
            'grand_ranks.*.abbreviation' => 'required|string|max:30|distinct:ignore_case',
            'grand_ranks.*.title' => 'nullable|string|max:120',
            'provincial_ranks' => 'nullable|array|max:200',
            'provincial_ranks.*.abbreviation' => 'required|string|max:30|distinct:ignore_case',
            'provincial_ranks.*.title' => 'nullable|string|max:120',

        ];
    }
}
