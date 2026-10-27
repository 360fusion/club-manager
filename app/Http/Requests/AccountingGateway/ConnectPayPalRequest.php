<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::connectPayPal().
 */
class ConnectPayPalRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => 'required|string|max:150',
            'paypal_client_id' => 'required|string',
            'paypal_client_secret' => 'required|string|max:500',
            'paypal_environment' => 'required|string|in:live,sandbox',
            'currency' => $this->currencyRule(),
            'opening_balance' => 'required|numeric|max:99999999.99',

        ];
    }
}
