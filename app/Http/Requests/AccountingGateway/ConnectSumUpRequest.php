<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::connectSumUp().
 */
class ConnectSumUpRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => 'required|string|max:150',
            'sumup_api_key' => 'required|string|max:500',
            'currency' => $this->currencyRule(),
            'opening_balance' => 'required|numeric|max:99999999.99',

        ];
    }
}
