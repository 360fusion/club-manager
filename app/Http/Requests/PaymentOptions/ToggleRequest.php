<?php

namespace App\Http\Requests\PaymentOptions;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PaymentOptionsController::toggle(): switch a payment option on or off for the whole lodge.
 */
class ToggleRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['is_active' => 'required|boolean'];
    }
}
