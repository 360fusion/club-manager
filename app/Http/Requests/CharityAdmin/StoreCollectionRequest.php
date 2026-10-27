<?php

namespace App\Http\Requests\CharityAdmin;

use App\Http\Requests\ClubRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to CharityAdminController::storeCollection().
 */
class StoreCollectionRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'collection_type' => 'required|string|max:50',
            'cash_amount' => 'required|numeric|min:0|max:99999999.99',
            'cheque_amount' => 'required|numeric|min:0|max:99999999.99',
            'counted_by_member_id' => ['nullable', Rule::exists('club_acc_members', 'id')->where('club_id', $club->id)],
            'witnessed_by_member_id' => ['nullable', Rule::exists('club_acc_members', 'id')->where('club_id', $club->id)],
            'donor_member_id' => ['nullable', Rule::exists('club_acc_members', 'id')->where('club_id', $club->id)],
            'donor_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:10000',
        ];
    }
}
