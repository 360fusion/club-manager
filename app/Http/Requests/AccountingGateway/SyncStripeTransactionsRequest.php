<?php

namespace App\Http\Requests\AccountingGateway;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingGatewayController::syncStripeTransactions().
 */
class SyncStripeTransactionsRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',

        ];
    }
}
