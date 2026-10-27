<?php

namespace App\Http\Requests\EventPayment;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to EventPaymentController::markPaid().
 */
class MarkPaidRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => 'nullable|numeric|min:0.01|max:99999999.99',
            'method' => 'nullable|string|max:100',
            'received_on' => 'nullable|date',
            'comment' => 'nullable|string|max:1000',

        ];
    }
}
