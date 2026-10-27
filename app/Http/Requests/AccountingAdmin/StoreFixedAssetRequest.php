<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAdminController::storeFixedAsset().
 */
class StoreFixedAssetRequest extends ClubRequest
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'bill_id' => ['nullable', 'exists:accounting_bills,id'],
            'depreciation_method' => ['required', 'string', 'in:straight_line,reducing_balance,none'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
