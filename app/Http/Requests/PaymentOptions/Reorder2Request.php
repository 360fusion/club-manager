<?php

namespace App\Http\Requests\PaymentOptions;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PaymentOptionsController::reorder().
 */
class Reorder2Request extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:50', 'ids.*' => 'integer|min:1|max:4294967295',
        ];
    }
}
