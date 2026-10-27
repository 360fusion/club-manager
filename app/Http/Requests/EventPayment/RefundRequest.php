<?php

namespace App\Http\Requests\EventPayment;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to EventPaymentController::refund().
 */
class RefundRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => 'required|string|min:3|max:1000',
            'amount' => 'nullable|numeric|min:0.01|max:99999999.99',

        ];
    }
}
