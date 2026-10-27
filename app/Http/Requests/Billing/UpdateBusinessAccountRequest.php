<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to BillingController::updateBusinessAccount().
 */
class UpdateBusinessAccountRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'company_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'billing_contact_email' => 'required|email|max:255',
            'billing_phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
        ];
    }
}
