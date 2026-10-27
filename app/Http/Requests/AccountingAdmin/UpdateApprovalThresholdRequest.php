<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAdminController::updateApprovalThreshold().
 */
class UpdateApprovalThresholdRequest extends ClubRequest
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
            'enabled' => ['required', 'boolean'],
            'amount' => ['nullable', 'required_if:enabled,true', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
