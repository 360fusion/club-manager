<?php

namespace App\Http\Requests\EventPayment;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to EventPaymentController::markUnpaid().
 */
class MarkUnpaidRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => 'required|string|min:3|max:1000',
        ];
    }
}
