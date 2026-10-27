<?php

namespace App\Http\Requests\Signature;

use App\Http\Requests\AppRequest;

/**
 * Declining to sign a document, with an optional reason.
 */
class DeclineRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => 'nullable|string|max:500'];
    }
}
