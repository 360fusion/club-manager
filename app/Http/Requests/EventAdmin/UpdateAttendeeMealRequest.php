<?php

namespace App\Http\Requests\EventAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to EventAdminController::updateAttendeeMeal().
 */
class UpdateAttendeeMealRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attending_dining' => 'nullable|boolean',
            'starter_item_id' => 'nullable|integer|min:1|max:4294967295',
            'main_item_id' => 'nullable|integer|min:1|max:4294967295',
            'dessert_item_id' => 'nullable|integer|min:1|max:4294967295',
            'dietary_requirements' => 'nullable|string|max:1000',

        ];
    }
}
