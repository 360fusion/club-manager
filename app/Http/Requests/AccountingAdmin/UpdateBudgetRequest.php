<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;
use Illuminate\Validation\Rule;

/**
 * The form posted to AccountingAdminController::updateBudget().
 */
class UpdateBudgetRequest extends ClubRequest
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
        $club = $this->club();

        return [
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'lines' => ['required', 'array'],
            'lines.*.account_id' => ['required', Rule::exists('accounting_accounts', 'id')->where('club_id', $club->id)],
            'lines.*.budgeted_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
