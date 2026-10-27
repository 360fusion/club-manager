<?php

namespace App\Http\Requests\UserAdmin;

use App\Enums\ClubRole;
use App\Http\Requests\ClubRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to UserAdminController::updateRole().
 */
class UpdateRoleRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(ClubRole::assignable())],
        ];
    }
}
