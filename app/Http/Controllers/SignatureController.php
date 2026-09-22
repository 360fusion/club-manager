<?php

namespace App\Http\Controllers;

use App\Services\Signatures\SignatureRequestService;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $httpRequest, string $token, SignatureRequestService $signatures): RedirectResponse
    {
        $signatureRequest = $signatures->findByToken($token);
        abort_if(! $signatureRequest, 404);

        $validated = $httpRequest->validate([
            'method' => 'required|in:typed,drawn',
            'typed_name' => 'required_if:method,typed|nullable|string|max:150',
            'image' => [
                'required_if:method,drawn', 'nullable', 'file',
                'mimes:'.UploadRules::IMAGE_TYPES, 'max:512',
                'dimensions:max_width='.UploadRules::MAX_IMAGE_SIDE.',max_height='.UploadRules::MAX_IMAGE_SIDE,
            ],
            'consent' => 'accepted',
        ]);

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

    public function decline(Request $httpRequest, string $token, SignatureRequestService $signatures): RedirectResponse
    {
        $signatureRequest = $signatures->findByToken($token);
        abort_if(! $signatureRequest, 404);

        $validated = $httpRequest->validate(['reason' => 'nullable|string|max:500']);

        try {
            $signatures->decline($signatureRequest, $validated['reason'] ?? null, (string) $httpRequest->ip(), (string) $httpRequest->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('sign.show', ['token' => $token]);
    }
}
