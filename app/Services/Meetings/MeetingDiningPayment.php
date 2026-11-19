<?php

namespace App\Services\Meetings;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubPlatformAccount;
use App\Models\MeetingRsvp;
use App\Models\PlatformPayment;
use App\Services\Payment\PlatformFees;
use App\Services\Payment\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Paying for meeting dining by card, through the lodge's own Stripe set-up (its keys, or its account connected to the
 * platform), the same card option events use. A payment covers what the reply owes when it is started: the member's
 * place and any unpaid dining guests. Bank transfer and paying on the night stay as they were.
 */
class MeetingDiningPayment
{
    public function __construct(private StripeGateway $stripe, private PlatformFees $fees) {}

    /**
     * The lodge's card option, when it is switched on and ready.
     */
    public function cardMethod(Club $club): ?ClubPaymentMethod
    {
        if (! config('events.online_payments')) {
            return null;
        }

        $method = ClubPaymentMethod::where('club_id', $club->id)->where('type', ClubPaymentMethod::CARD)->where('is_active', true)->first();

        return $method?->hasCompleteDetails() ? $method : null;
    }

    public function canPay(MeetingRsvp $rsvp): bool
    {
        $meeting = $rsvp->meeting;

        return MeetingReplies::owed($meeting, $rsvp)['amount'] > 0
            && $meeting->status === 'published'
            && ! $meeting->club->hasPassed($meeting->meeting_date->copy()->endOfDay())
            && $this->cardMethod($meeting->club) !== null;
    }

    /**
     * Start a Stripe Checkout page for what this reply owes and return its address.
     */
    public function start(MeetingRsvp $rsvp, string $successUrl, string $cancelUrl): string
    {
        $rsvp->loadMissing(['meeting.club', 'guests', 'user']);

        if (! $this->canPay($rsvp)) {
            throw ValidationException::withMessages(['payment' => 'There is nothing to pay by card for this reply.']);
        }

        $meeting = $rsvp->meeting;
        $club = $meeting->club;
        $method = $this->cardMethod($club);
        $owed = MeetingReplies::owed($meeting, $rsvp);
        $connected = $method->usesConnect() ? $club->platformAccount : null;
        $amountMinor = (int) round($owed['amount'] * 100);
        $commission = $connected ? $this->fees->commissionMinor($amountMinor, $connected) : 0;

        // Starting again closes the earlier page, so the same dining cannot be paid twice.
        if ($rsvp->stripe_session_id) {
            $this->stripe->expireCheckoutSession($method, $rsvp->stripe_session_id, $connected?->stripe_account_id);
        }

        $metadata = [
            'meeting_rsvp_id' => (string) $rsvp->id,
            'meeting_id' => (string) $meeting->id,
            'club_id' => (string) $club->id,
            'member' => $owed['member'] ? '1' : '0',
            'guests' => implode(',', $owed['guest_ids']),
        ];

        $session = $this->stripe->createCheckoutSession($method, [
            'mode' => 'payment',
            'client_reference_id' => 'meeting-rsvp-'.$rsvp->id,
            'customer_email' => $rsvp->user?->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($club->currencyCode()),
                    'unit_amount' => $amountMinor,
                    'product_data' => ['name' => 'Dining: '.$meeting->title, 'description' => $club->name.' · reference '.$rsvp->payment_reference],
                ],
            ]],
            'metadata' => $metadata + ($connected ? ['platform_fee' => (string) $commission] : []),
            'payment_intent_data' => ['metadata' => ['meeting_rsvp_id' => (string) $rsvp->id, 'reference' => (string) $rsvp->payment_reference]] + ($commission > 0 ? ['application_fee_amount' => $commission] : []),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], $connected?->stripe_account_id);

        $rsvp->forceFill(['stripe_session_id' => $session['id']])->save();

        return $session['url'];
    }

    /**
     * Record a paid Checkout session for meeting dining once (called from the Stripe webhooks). Marks paid exactly the
     * places the payment was started for. Returns a short reason when nothing was done, null when it was recorded.
     */
    public function recordCheckoutSession(Club $club, object $session, ?ClubPlatformAccount $account): ?string
    {
        $rsvp = MeetingRsvp::with(['meeting', 'guests'])->find((int) ($session->metadata->meeting_rsvp_id ?? 0));
        $ours = $rsvp && ($rsvp->stripe_session_id === $session->id || (string) ($session->metadata->club_id ?? '') === (string) $club->id);

        if (! $rsvp || $rsvp->meeting->club_id !== $club->id || ! $ours) {
            Log::warning('Stripe dining payment did not match a meeting reply', ['club' => $club->id, 'session' => $session->id ?? null]);

            return 'no matching meeting reply';
        }

        $externalId = (string) ($session->payment_intent ?? $session->id);

        if (MeetingRsvp::where('stripe_payment_id', $externalId)->exists()) {
            return 'already recorded';
        }

        $paid = ['payment_status' => 'paid', 'payment_method' => 'online', 'paid_at' => now(), 'paid_recorded_by' => null];
        $guestIds = array_filter(array_map('intval', explode(',', (string) ($session->metadata->guests ?? ''))));

        DB::transaction(function () use ($rsvp, $session, $paid, $guestIds, $externalId) {
            $rsvp->forceFill(['stripe_payment_id' => $externalId] + (($session->metadata->member ?? '0') === '1' ? $paid : []))->save();
            $rsvp->guests()->whereIn('id', $guestIds)->update($paid);
        });

        if ($account) {
            $amount = round(((int) $session->amount_total) / 100, 2);

            PlatformPayment::updateOrCreate(['payment_intent' => $externalId], [
                'club_id' => $club->id,
                'registration_id' => null,
                'currency' => strtoupper((string) ($session->currency ?? $club->currencyCode())),
                'gross' => $amount,
                'commission' => round(min((int) ($session->metadata->platform_fee ?? 0), (int) $session->amount_total) / 100, 2),
            ]);
        }

        return null;
    }
}
