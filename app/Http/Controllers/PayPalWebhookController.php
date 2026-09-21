<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\Events\EventOnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use UnexpectedValueException;

/**
 * PayPal telling a lodge's booking system that a payment was captured. Each lodge gives PayPal its own address
 * (…/webhooks/paypal/{club id}); PayPal itself is asked to confirm every notification is genuine.
 */
class PayPalWebhookController extends Controller
{
    public function handle(Request $request, int $clubId, EventOnlinePayment $online): JsonResponse
    {
        $club = Club::find($clubId);

        // The same answer for an unknown lodge as for a bad signature, so this cannot be used to probe.
        if (! $club) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        $headers = collect($request->headers->all())->map(fn (array $values) => (string) ($values[0] ?? ''))->all();

        try {
            $result = $online->handlePayPalWebhook($club, $request->getContent(), $headers);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        return response()->json(['received' => true, 'note' => $result]);
    }
}
