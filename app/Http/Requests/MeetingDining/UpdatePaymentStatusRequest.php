<?php

namespace App\Http\Requests\MeetingDining;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to MeetingDiningController::updatePaymentStatus().
 */
class UpdatePaymentStatusRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'payment_status' => 'required|in:unpaid,paid,waived,refunded',
            'payment_reference' => 'nullable|string|max:255',

        ];
    }
}
