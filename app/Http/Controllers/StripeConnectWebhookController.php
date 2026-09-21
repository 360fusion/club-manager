<?php

namespace App\Http\Controllers;

use App\Services\Events\EventOnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe telling the platform about lodges' connected accounts: a payment was made, or an account's status changed.
 * There is one address for every lodge, signed with the platform's Connect secret; nothing is believed without a valid signature.
 */
class StripeConnectWebhookController extends Controller
{
    public function handle(Request $request, EventOnlinePayment $online): JsonResponse
    {
        try {
            $result = $online->handleConnectWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        return response()->json(['received' => true, 'note' => $result]);
    }
}
