<?php

namespace App\Http\Requests\SuperAdminLodgeClaim;

use App\Http\Requests\AppRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to SuperAdminLodgeClaimController::approve().
 */
class ApproveRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['new', 'link'])],
            'club_id' => ['required_if:mode,link', 'nullable', 'exists:clubs,id'],
            'make_claimant_admin' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',

        ];
    }
}
