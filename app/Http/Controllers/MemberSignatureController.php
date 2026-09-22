<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Services\Signatures\SignatureRequestService;
use App\Support\ImageDownscaler;
use App\Support\MemberScope;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The in-app equivalent of the public /sign/{token} page, for a signer who is already logged in: reached from a
 * notification or the dashboard inbox instead of an emailed link, and authorised by session instead of a token.
 */
class MemberSignatureController extends Controller
{
    public function show(Request $httpRequest, string $slug, int $id, SignatureRequestService $signatures): Response
    {
        $request = $this->authorized($httpRequest, $slug, $id, $signatures);

        return Inertia::render('Member/SignDocument', [
            'club' => ['slug' => $request->club->slug, 'name' => $request->club->name],
            'id' => $request->id,
            'status' => $request->status->value,
            'documentLabel' => $request->signable->signatureLabel($request->purpose),
            'signedAt' => $request->signed_at?->format('j M Y, H:i'),
        ]);
    }

    public function store(Request $httpRequest, string $slug, int $id, SignatureRequestService $signatures): RedirectResponse
    {
        $request = $this->authorized($httpRequest, $slug, $id, $signatures);

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
            $signatures->sign($request, $validated['method'], [
                'typed_name' => $validated['typed_name'] ?? null,
                'image' => $httpRequest->file('image'),
            ], (string) $httpRequest->ip(), (string) $httpRequest->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('member.signatures.show', ['slug' => $slug, 'id' => $id])->with('success', 'Signed, thank you.');
    }

    public function decline(Request $httpRequest, string $slug, int $id, SignatureRequestService $signatures): RedirectResponse
    {
        $request = $this->authorized($httpRequest, $slug, $id, $signatures);

        $validated = $httpRequest->validate(['reason' => 'nullable|string|max:500']);

        try {
            $signatures->decline($request, $validated['reason'] ?? null, (string) $httpRequest->ip(), (string) $httpRequest->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('member.signatures.show', ['slug' => $slug, 'id' => $id]);
    }

    private function authorized(Request $httpRequest, string $slug, int $id, SignatureRequestService $signatures): SignatureRequest
    {
        $scope = MemberScope::for($httpRequest->user(), $slug);
        $request = SignatureRequest::where('club_id', $scope->club->id)->findOrFail($id);

        abort_unless($signatures->signerUser($request)?->is($scope->user), 403);

        return $request;
    }
}
