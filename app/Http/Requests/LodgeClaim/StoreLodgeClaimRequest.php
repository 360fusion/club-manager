<?php

namespace App\Http\Requests\LodgeClaim;

use App\Http\Requests\ClubRequest;
use App\Models\LodgeClaim;
use Illuminate\Validation\Rule;

/**
 * The form posted to LodgeClaimController::store().
 */
class StoreLodgeClaimRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'claimant_role' => ['required', Rule::in(array_keys(LodgeClaim::ROLES))],
            'message' => 'required|string|min:10|max:2000',
            'phone' => 'nullable|string|max:50',
            'evidence' => 'nullable|string|max:2000',
            'confirm' => 'accepted',

        ];
    }
}
