<?php

namespace App\Services\Meetings;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubPlatformAccount;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Models\PlatformPayment;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\PlatformFees;
use App\Services\Payment\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Paying for meeting dining online, through the lodge's own card (Stripe) and PayPal options, the same ones events
 * use. A payment covers what the reply owes when it is started: the member's place and any unpaid dining guests, and
 * each place remembers which payment paid it, so the Secretary can refund that place through the same provider.
 * Bank transfer and paying on the night stay as they were.
 */
class MeetingDiningPayment
{
    public const CARD = 'card';

    public const PAYPAL = 'paypal';

    public function __construct(private StripeGateway $stripe, private PayPalGateway $paypal, private PlatformFees $fees) {}

    /**
     * The lodge's card or PayPal option, when it is switched on and ready for the lodge's currency.
     */
    public function method(Club $club, string $provider): ?ClubPaymentMethod
    {
        if (! config('events.online_payments')) {
            return null;
        }

        $type = $provider === self::PAYPAL ? ClubPaymentMethod::PAYPAL : ClubPaymentMethod::CARD;
        $method = ClubPaymentMethod::where('club_id', $club->id)->where('type', $type)->where('is_active', true)->first();

        return $method?->isReadyFor($club->currencyCode()) ? $method : null;
    }

    /**
     * The ways this reply can pay online now: nothing when nothing is owed or the meeting is over.
     *
     * @return list<string>
     */
    public function providers(MeetingRsvp $rsvp): array
    {
        $meeting = $rsvp->meeting;

        if (MeetingReplies::owed($meeting, $rsvp)['amount'] <= 0 || $meeting->status !== 'published' || $meeting->club->hasPassed($meeting->meeting_date->copy()->endOfDay())) {
            return [];
        }

        return array_values(array_filter([self::CARD, self::PAYPAL], fn (string $provider) => $this->method($meeting->club, $provider) !== null));
    }

    /**
     * Start paying what this reply owes and return the address to send the payer to.
     */
    public function start(MeetingRsvp $rsvp, string $provider, string $successUrl, string $cancelUrl, ?string $payPalReturnUrl = null): string
    {
        $rsvp->loadMissing(['meeting.club', 'guests', 'user']);

        if (! in_array($provider, $this->providers($rsvp), true)) {
            throw ValidationException::withMessages(['payment' => 'There is nothing to pay this way for this reply.']);
        }

        $meeting = $rsvp->meeting;
        $club = $meeting->club;
        $method = $this->method($club, $provider);
        $owed = MeetingReplies::owed($meeting, $rsvp);

        if ($provider === self::PAYPAL) {
            $order = $this->paypal->createOrder($method, [
                'amount' => $owed['amount'],
                'currency' => $club->currencyCode(),
                'reference' => (string) $rsvp->payment_reference,
                'custom_id' => self::customId($rsvp->id, $owed['member'], $owed['guest_ids']),
                'description' => 'Dining: '.$meeting->title.' ('.$rsvp->payment_reference.')',
                'brand' => $club->name,
                'return_url' => $payPalReturnUrl ?? $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            $rsvp->forceFill(['paypal_order_id' => $order['id']])->save();

            return $order['url'];
        }

        $connected = $method->usesConnect() ? $club->platformAccount : null;
        $amountMinor = (int) round($owed['amount'] * 100);
        $commission = $connected ? $this->fees->commissionMinor($amountMinor, $connected) : 0;

        // Starting again closes the earlier page, so the same dining cannot be paid twice.
        if ($rsvp->stripe_session_id) {
            $this->stripe->expireCheckoutSession($method, $rsvp->stripe_session_id, $connected?->stripe_account_id);
        }

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
            'metadata' => [
                'meeting_rsvp_id' => (string) $rsvp->id,
                'meeting_id' => (string) $meeting->id,
                'club_id' => (string) $club->id,
                'member' => $owed['member'] ? '1' : '0',
                'guests' => implode(',', $owed['guest_ids']),
            ] + ($connected ? ['platform_fee' => (string) $commission] : []),
            'payment_intent_data' => ['metadata' => ['meeting_rsvp_id' => (string) $rsvp->id, 'reference' => (string) $rsvp->payment_reference]] + ($commission > 0 ? ['application_fee_amount' => $commission] : []),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], $connected?->stripe_account_id);

        $rsvp->forceFill(['stripe_session_id' => $session['id']])->save();

        return $session['url'];
    }

    /**
     * Record a paid Stripe Checkout session for meeting dining once (from the Stripe webhooks). Returns a short reason
     * when nothing was done, null when it was recorded.
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
        $guestIds = array_values(array_filter(array_map('intval', explode(',', (string) ($session->metadata->guests ?? '')))));
        $result = $this->markPaid($rsvp, ($session->metadata->member ?? '0') === '1', $guestIds, 'stripe', $externalId);

        if ($result === null && $account) {
            PlatformPayment::updateOrCreate(['payment_intent' => $externalId], [
                'club_id' => $club->id,
                'registration_id' => null,
                'currency' => strtoupper((string) ($session->currency ?? $club->currencyCode())),
                'gross' => round(((int) $session->amount_total) / 100, 2),
                'commission' => round(min((int) ($session->metadata->platform_fee ?? 0), (int) $session->amount_total) / 100, 2),
            ]);
        }

        return $result;
    }

    /**
     * The payer came back from PayPal having approved the order: take the payment. Only the order this reply started
     * can be captured.
     */
    public function capturePayPal(MeetingRsvp $rsvp, string $orderId): bool
    {
        $rsvp->loadMissing(['meeting.club', 'guests']);
        $method = $this->method($rsvp->meeting->club, self::PAYPAL);

        if (! $method || ! $rsvp->paypal_order_id || ! hash_equals($rsvp->paypal_order_id, $orderId)) {
            return false;
        }

        // Back from PayPal again (a refresh, or the webhook got there first): nothing is left to take.
        if (MeetingReplies::owed($rsvp->meeting, $rsvp)['amount'] <= 0) {
            return true;
        }

        $result = $this->recordPayPalCapture($rsvp, $this->paypal->captureOrder($method, $orderId));

        return $result === null || $result === 'already recorded';
    }

    /**
     * A PayPal webhook capture for meeting dining (its custom id starts "mr"). Returns why nothing was done, or null.
     *
     * @param  array<string, mixed>  $resource
     */
    public function handlePayPalCapture(Club $club, array $resource): ?string
    {
        $parsed = self::parseCustomId((string) ($resource['custom_id'] ?? ''));
        $rsvp = $parsed ? MeetingRsvp::with(['meeting.club', 'guests'])->find($parsed['rsvp']) : null;
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! $rsvp || $rsvp->meeting->club_id !== $club->id || ! $orderId || $rsvp->paypal_order_id !== $orderId) {
            Log::warning('PayPal dining payment did not match a meeting reply', ['club' => $club->id, 'capture' => $resource['id'] ?? null]);

            return 'no matching meeting reply';
        }

        return $this->recordPayPalCapture($rsvp, [
            'status' => (string) ($resource['status'] ?? ''),
            'capture_id' => isset($resource['id']) ? (string) $resource['id'] : null,
            'amount' => (float) ($resource['amount']['value'] ?? 0),
            'currency' => $resource['amount']['currency_code'] ?? null,
            'custom_id' => $resource['custom_id'] ?? null,
        ]);
    }

    /**
     * @param  array{status: string, capture_id: ?string, amount: float, currency: ?string, custom_id: ?string}  $capture
     */
    private function recordPayPalCapture(MeetingRsvp $rsvp, array $capture): ?string
    {
        $parsed = self::parseCustomId((string) $capture['custom_id']);

        if ($capture['status'] !== 'COMPLETED' || ! $capture['capture_id']) {
            return 'not paid yet';
        }

        if (! $parsed || $parsed['rsvp'] !== $rsvp->id || strtoupper((string) $capture['currency']) !== strtoupper($rsvp->meeting->club->currencyCode())) {
            Log::warning('PayPal capture did not match the meeting reply', ['rsvp' => $rsvp->id, 'capture' => $capture['capture_id']]);

            return 'no matching meeting reply';
        }

        return $this->markPaid($rsvp, $parsed['member'], $parsed['guests'], 'paypal', $capture['capture_id']);
    }

    /**
     * Mark the places a payment was started for as paid, once per payment.
     *
     * @param  list<int>  $guestIds
     */
    private function markPaid(MeetingRsvp $rsvp, bool $member, array $guestIds, string $provider, string $externalId): ?string
    {
        if (MeetingRsvp::where('online_payment_id', $externalId)->exists() || MeetingRsvpGuest::where('online_payment_id', $externalId)->exists()) {
            return 'already recorded';
        }

        $paid = ['payment_status' => 'paid', 'payment_method' => 'online', 'paid_at' => now(), 'paid_recorded_by' => null, 'online_provider' => $provider, 'online_payment_id' => $externalId];

        DB::transaction(function () use ($rsvp, $member, $guestIds, $paid, $provider, $externalId) {
            if ($member) {
                $rsvp->forceFill($paid + ($provider === 'stripe' ? ['stripe_payment_id' => $externalId] : []))->save();
            }

            MeetingRsvpGuest::where('meeting_rsvp_id', $rsvp->id)->whereIn('id', $guestIds)->update($paid);
        });

        return null;
    }

    /**
     * Refund one dining place, through the provider that took it when it was paid online; a place paid by bank
     * transfer or cash is only marked refunded (the money goes back by hand).
     */
    public function refund(MeetingRsvp $rsvp, ?MeetingRsvpGuest $guest = null): void
    {
        $rsvp->loadMissing('meeting.club');
        $meeting = $rsvp->meeting;
        $club = $meeting->club;
        $place = $guest ?? $rsvp;

        if ($place->payment_status !== 'paid') {
            throw ValidationException::withMessages(['refund' => 'Only a paid place can be refunded.']);
        }

        $amount = $guest ? (float) ($guest->dining_fee ?: $meeting->dining_cost_guest) : (float) $meeting->dining_cost_member;
        $key = 'meeting-refund-'.($guest ? 'guest-'.$guest->id : 'rsvp-'.$rsvp->id);

        if ($place->online_payment_id && $place->online_provider === 'stripe') {
            $method = $this->method($club, self::CARD) ?? throw ValidationException::withMessages(['refund' => 'The card option is not set up, so this cannot be refunded here.']);
            $connected = $method->usesConnect() ? $club->platformAccount : null;
            $this->stripe->refund($method, $place->online_payment_id, (int) round($amount * 100), $connected?->stripe_account_id, $key);
        } elseif ($place->online_payment_id && $place->online_provider === 'paypal') {
            $method = $this->method($club, self::PAYPAL) ?? throw ValidationException::withMessages(['refund' => 'The PayPal option is not set up, so this cannot be refunded here.']);
            $this->paypal->refund($method, $place->online_payment_id, $amount, $club->currencyCode(), $key);
        }

        $place->forceFill(['payment_status' => 'refunded', 'refunded_at' => now()])->save();
    }

    /**
     * @param  list<int>  $guestIds
     */
    public static function customId(int $rsvpId, bool $member, array $guestIds): string
    {
        return 'mr'.$rsvpId.':'.($member ? '1' : '0').':'.implode(',', $guestIds);
    }

    /**
     * @return array{rsvp: int, member: bool, guests: list<int>}|null
     */
    public static function parseCustomId(string $customId): ?array
    {
        if (! preg_match('/^mr(\d+):([01]):([\d,]*)$/', $customId, $m)) {
            return null;
        }

        return ['rsvp' => (int) $m[1], 'member' => $m[2] === '1', 'guests' => array_values(array_filter(array_map('intval', explode(',', $m[3]))))];
    }
}
