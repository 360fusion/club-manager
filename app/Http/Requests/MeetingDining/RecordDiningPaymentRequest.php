<?php

namespace App\Http\Requests\MeetingDining;

use App\Http\Controllers\MeetingDiningController;
use App\Http\Requests\ClubRequest;

/**
 * The form posted to MeetingDiningController::recordDiningPayment().
 */
class RecordDiningPaymentRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => 'required|in:member,guest',
            'diner_id' => 'required|integer',
            'payment_status' => 'required|in:unpaid,paid,waived,refunded',
            'payment_method' => 'nullable|in:'.implode(',', MeetingDiningController::DINING_PAYMENT_METHODS),
            'payment_reference' => 'nullable|string|max:255',

        ];
    }
}
