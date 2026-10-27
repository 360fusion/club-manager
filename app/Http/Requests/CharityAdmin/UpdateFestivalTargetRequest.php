<?php

namespace App\Http\Requests\CharityAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to CharityAdminController::updateFestivalTarget().
 */
class UpdateFestivalTargetRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'festival_name' => 'required|string|max:150',
            'relief_chest_ref' => 'required|string|max:50',
            'target_amount' => 'required|numeric|min:0|max:99999999.99',
            'bronze_tier' => 'required|numeric|min:0|max:99999999.99',
            'silver_tier' => 'required|numeric|min:0|max:99999999.99',
            'gold_tier' => 'required|numeric|min:0|max:99999999.99',
            'platinum_tier' => 'required|numeric|min:0|max:99999999.99',
        ];
    }
}
