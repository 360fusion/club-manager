<?php

namespace App\Http\Requests\CharityAdmin;

use App\Http\Requests\ClubRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to CharityAdminController::storeGrant().
 */
class StoreGrantRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'recipient_name' => 'required|string|max:150',
            'purpose' => 'required|string|max:10000',
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'relief_chest_number' => 'nullable|string|max:50',
            'proposer_member_id' => ['nullable', Rule::exists('club_acc_members', 'id')->where('club_id', $club->id)],
            'seconder_member_id' => ['nullable', Rule::exists('club_acc_members', 'id')->where('club_id', $club->id)],
            'meeting_id' => ['nullable', Rule::exists('meetings', 'id')->where('club_id', $club->id)],
        ];
    }
}
