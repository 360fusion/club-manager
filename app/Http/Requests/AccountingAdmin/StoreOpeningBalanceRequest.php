<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::storeOpeningBalance().
 */
class StoreOpeningBalanceRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounting_accounts,id'],
            'opening_balance' => ['required', 'numeric', 'max:99999999.99'],
            'as_of_date' => ['nullable', 'date'],
        ];
    }
}
