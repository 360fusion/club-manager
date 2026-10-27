<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAdminController::updateCharityCommission().
 */
class UpdateCharityCommissionRequest extends ClubRequest
{
    public function authorize(): bool
    {
        return ClubAccess::can($this->user(), $this->club(), 'manage_billing');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registered' => ['required', 'boolean'],
            'charity_number' => ['nullable', 'required_if:registered,true', 'string', 'max:30'],
        ];
    }
}
