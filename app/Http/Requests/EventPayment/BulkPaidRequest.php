<?php

namespace App\Http\Requests\EventPayment;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to EventPaymentController::bulkPaid().
 */
class BulkPaidRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registration_ids' => 'required|array|min:1|max:200',
            'registration_ids.*' => 'integer|max:4294967295',
            'method' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:1000',

        ];
    }
}
