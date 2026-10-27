<?php

namespace App\Http\Controllers;

use App\Http\Requests\Signature\DeclineRequest;
use App\Http\Requests\Signature\SignRequest;
use App\Services\Signatures\SignatureRequestService;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The public, no-login page a signer reaches from the emailed link: read what is being signed, then sign it
 * by typing or drawing a signature, or decline. The token itself is the credential.
 */
class SignatureController extends Controller
{
    public function show(string $token, SignatureRequestService $signatures): Response
    {
        $request = $signatures->findByToken($token);

        if (! $request) {
            return Inertia::render('Public/SignDocument', ['found' => false]);
        }

        $request->loadMissing('club');

        return Inertia::render('Public/SignDocument', [
            'found' => true,
            'token' => $token,
            'status' => $request->status->value,
            'clubName' => $request->club->name,
            'signerName' => $request->signer_name,
            'documentLabel' => $request->signable->signatureLabel($request->purpose),
            'signedAt' => $request->signed_at?->format('j M Y, H:i'),
            'method' => $request->method,
            'typedName' => $request->typed_name,
        ]);
    }

    public function store(SignRequest $httpRequest, string $token, SignatureRequestService $signatures): RedirectResponse
    {
        $signatureRequest = $signatures->findByToken($token);
        abort_if(! $signatureRequest, 404);

        $validated = $httpRequest->validated();

        if ($validated['method'] === 'drawn' && $httpRequest->hasFile('image')) {
            if ($error = UploadRules::assertSafeUpload($httpRequest->file('image'))) {
                return back()->withErrors(['image' => $error]);
            }

            ImageDownscaler::apply($httpRequest->file('image'));
        }

        try {
            $signatures->sign($signatureRequest, $validated['method'], [
                'typed_name' => $validated['typed_name'] ?? null,
                'image' => $httpRequest->file('image'),
            ], (string) $httpRequest->ip(), (string) $httpRequest->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('sign.show', ['token' => $token])->with('success', 'Thank you, your signature has been recorded.');
    }

    public function decline(DeclineRequest $httpRequest, string $token, SignatureRequestService $signatures): RedirectResponse
    {
        $signatureRequest = $signatures->findByToken($token);
        abort_if(! $signatureRequest, 404);

        $validated = $httpRequest->validated();

        try {
            $signatures->decline($signatureRequest, $validated['reason'] ?? null, (string) $httpRequest->ip(), (string) $httpRequest->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('sign.show', ['token' => $token]);
    }
}
