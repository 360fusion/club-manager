<?php

namespace App\Http\Requests\Signature;

use App\Http\Requests\AppRequest;
use App\Support\UploadRules;

/**
 * Signing a document, by token (SignatureController) or from the member area (MemberSignatureController): a typed
 * name or a drawn image, and the signer's consent.
 */
class SignRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => 'required|in:typed,drawn',
            'typed_name' => 'required_if:method,typed|nullable|string|max:150',
            'image' => [
                'required_if:method,drawn', 'nullable', 'file',
                'mimes:'.UploadRules::IMAGE_TYPES, 'max:512',
                'dimensions:max_width='.UploadRules::MAX_IMAGE_SIDE.',max_height='.UploadRules::MAX_IMAGE_SIDE,
            ],
            'consent' => 'accepted',
        ];
    }
}
