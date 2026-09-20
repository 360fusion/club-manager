<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\DirectDebitMandate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoCardlessWebhookController extends Controller
{
    /**
     * Handle incoming GoCardless webhooks for a specific club.
     */
    public function handle(Request $request, int $clubId): JsonResponse
    {
        $signature = $request->header('Webhook-Signature');
        $rawPayload = $request->getContent();

        $bankAccount = BankAccount::where('club_id', $clubId)
            ->whereNotNull('gocardless_access_token')
            ->first();

        if (! $bankAccount) {
            return response()->json(['error' => 'No GoCardless configuration found for club.'], 404);
        }

        $secret = $bankAccount->gocardless_webhook_secret;

        // Fail closed. Without a configured secret the payload cannot be verified,
        // and accepting it would let anyone forge mandate events for this club.
        if (! $secret) {
            Log::warning("GoCardless webhook rejected for club {$clubId}: no webhook secret configured.");

            return response()->json(['error' => 'Webhook signature verification is not configured.'], 403);
        }

        $computedSignature = hash_hmac('sha256', $rawPayload, $secret);

        if (! hash_equals($computedSignature, (string) $signature)) {
            Log::warning("GoCardless webhook signature mismatch for club {$clubId}");

            return response()->json(['error' => 'Invalid signature'], 498);
        }

        $events = $request->input('events', []);

        foreach ($events as $event) {
            $resourceType = $event['resource_type'] ?? null;
            $action = $event['action'] ?? null;

            if ($resourceType === 'mandates') {
                $mandateId = $event['links']['mandate'] ?? null;
                if ($mandateId) {
                    $mandate = DirectDebitMandate::where('gocardless_mandate_id', $mandateId)->first();
                    if ($mandate) {
                        if ($action === 'active') {
                            $mandate->update(['status' => 'active']);
                        } elseif (in_array($action, ['cancelled', 'failed', 'expired'])) {
                            $mandate->update(['status' => $action]);
                        }
                    }
                }
            } elseif ($resourceType === 'payments') {
                $paymentId = $event['links']['payment'] ?? null;
                Log::info("GoCardless payment event [{$action}] for payment {$paymentId}");
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
