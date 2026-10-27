<?php

namespace App\Http\Requests\UserAdmin;

use App\Enums\ClubRole;
use App\Http\Requests\ClubRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to UserAdminController::storeMember().
 */
class StoreMemberRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => ['required', Rule::in(ClubRole::assignable())],
            'rank' => 'nullable|string|max:100',
            'committee_role' => 'nullable|in:chair,secretary,member',
            'member_number' => 'nullable|string|max:100',
            'send_invite' => 'nullable|boolean',
        ];
    }
}
