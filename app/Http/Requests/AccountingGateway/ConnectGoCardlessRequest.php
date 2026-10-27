<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::connectGoCardless().
 */
class ConnectGoCardlessRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => 'required|string|max:150',
            'gocardless_access_token' => 'required|string|max:500',
            'gocardless_environment' => 'required|string|in:sandbox,live',
            'gocardless_webhook_secret' => 'nullable|string|max:500',
            'currency' => $this->currencyRule(),
            'opening_balance' => 'required|numeric|max:99999999.99',

        ];
    }
}
