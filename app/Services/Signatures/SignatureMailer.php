<?php

namespace App\Services\Signatures;

use App\Mail\SignatureRequestMail;
use App\Models\SignatureRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the signature-request email. A mail problem is logged and never stops the request being recorded.
 */
class SignatureMailer
{
    public function requested(SignatureRequest $request): void
    {
        if (! $request->signer_email || ! $request->plainToken) {
            return;
        }

        try {
            Mail::to($request->signer_email)->send(new SignatureRequestMail(
                $request,
                route('sign.show', ['token' => $request->plainToken]),
            ));
        } catch (Throwable $e) {
            Log::warning('Signature request email could not be sent', ['request' => $request->id, 'error' => $e->getMessage()]);
        }
    }
}
