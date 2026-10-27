<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::storeBankAccount().
 */
class StoreBankAccountRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string|in:current,savings,credit_card,payment_gateway,merchant,cash',
            'account_number' => 'nullable|string|max:50',
            'sort_code' => 'nullable|string|max:20',
            'currency' => $this->currencyRule(),
            'opening_balance' => 'required|numeric|max:99999999.99',

        ];
    }
}
