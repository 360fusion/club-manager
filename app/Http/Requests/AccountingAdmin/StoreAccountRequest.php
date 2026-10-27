<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::storeAccount().
 */
class StoreAccountRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'code' => ['required', 'string', 'max:30', 'unique:accounting_accounts,code,NULL,id,club_id,'.$club->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'opening_balance' => ['nullable', 'numeric', 'max:99999999.99'],
            'as_of_date' => ['nullable', 'date'],
        ];
    }
}
