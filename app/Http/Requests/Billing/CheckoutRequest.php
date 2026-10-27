<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to BillingController::checkout().
 */
class CheckoutRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_id' => 'required|string',
        ];
    }
}
