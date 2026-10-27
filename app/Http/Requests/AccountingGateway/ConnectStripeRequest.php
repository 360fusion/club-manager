<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::connectStripe().
 */
class ConnectStripeRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => 'required|string|max:150',
            'stripe_secret_key' => 'required|string|max:500',
            'currency' => $this->currencyRule(),
            'opening_balance' => 'required|numeric|max:99999999.99',

        ];
    }
}
