<?php

namespace App\Http\Requests\StripeConnect;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to StripeConnectController::acceptTerms().
 */
class AcceptTermsRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agree' => 'accepted',
        ];
    }
}
