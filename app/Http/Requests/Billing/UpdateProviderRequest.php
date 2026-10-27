<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to BillingController::updateProvider().
 */
class UpdateProviderRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => 'required|in:stripe,paddle',
        ];
    }
}
