<?php

namespace App\Http\Requests\AccountingYearEnd;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingYearEndController::signOffYearAudit().
 */
class SignOffYearAuditRequest extends ClubRequest
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
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'auditor_one_user_id' => ['required', 'different:auditor_two_user_id', 'exists:users,id'],
            'auditor_two_user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
