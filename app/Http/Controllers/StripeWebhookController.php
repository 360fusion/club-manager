<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\Events\EventOnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe telling a lodge's booking system that a card payment was made. Each lodge gives Stripe its own
 * address (…/webhooks/stripe/{club id}) and signing secret; nothing is believed without a valid signature.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, int $clubId, EventOnlinePayment $online): JsonResponse
    {
        $club = Club::find($clubId);

        // The same answer for an unknown lodge as for a bad signature, so this cannot be used to probe.
        if (! $club) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        try {
            $result = $online->handleWebhook($club, $request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        return response()->json(['received' => true, 'note' => $result]);
    }
}
