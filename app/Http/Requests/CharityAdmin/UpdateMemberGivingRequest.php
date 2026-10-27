<?php

namespace App\Http\Requests\CharityAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to CharityAdminController::updateMemberGiving().
 */
class UpdateMemberGivingRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'regular_giving_amount' => 'required|numeric|min:0|max:99999999.99',
            'total_donated_to_date' => 'required|numeric|min:0|max:99999999.99',
            'qualifies_for_jewel' => 'boolean',
            'qualifies_for_bar' => 'boolean',

        ];
    }
}
