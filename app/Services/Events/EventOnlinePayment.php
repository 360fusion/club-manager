<?php

namespace App\Services\Events;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\EventPaymentLog;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\StripeGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Paying a booking online, by card on Stripe's own page or on PayPal's, using the lodge's own accounts, and
 * hearing back when the payment is made. The amount always comes from the booking, never from the browser.
 */
class EventOnlinePayment
{
    public function __construct(private StripeGateway $stripe, private PayPalGateway $paypal, private EventRegistrationService $registrations, private EventPaymentService $payments, private EventPricing $pricing) {}

    /**
     * The online options (card, PayPal) this booking's event offers and that are ready to take a payment.
     *
     * @return Collection<int, EventPaymentMethod>
     */
    public function options(EventRegistration $registration): Collection
    {
        if (! config('events.online_payments')) {
            return collect();
        }

        return $this->pricing->enabledMethods($registration->event)->filter(fn (EventPaymentMethod $o) => $o->method->isOnline())->values();
    }

    /**
     * The option to pay with: the one asked for, else the one the booking already chose, else the first.
     */
    public function optionFor(EventRegistration $registration, ?int $optionId = null): ?EventPaymentMethod
    {
        $options = $this->options($registration);

        if ($optionId !== null) {
            return $options->firstWhere('id', $optionId);
        }

        return $options->first(fn (EventPaymentMethod $o) => $o->method->id === $registration->payment_method_id) ?? $options->first();
    }

    public function canPay(EventRegistration $registration): bool
    {
        return $registration->status === 'attending'
            && ! in_array($registration->payment_status, ['paid', 'waived', 'refunded'], true)
            && (float) $registration->total > 0
            && ! in_array($registration->event->status, ['draft', 'cancelled', 'completed'], true)
            && $this->options($registration)->isNotEmpty();
    }

    /**
     * What the booking would cost if paid online now (the online price), for "pay now and save".
     *
     * @return array{total: float, saving: float}|null
     */
    public function onlineOffer(EventRegistration $registration, ?EventPaymentMethod $option = null): ?array
    {
        $option ??= $this->canPay($registration) ? $this->optionFor($registration) : null;

        if (! $option) {
            return null;
        }

        if ($registration->payment_method_id === $option->method->id || (float) $registration->amount_paid > 0) {
            return ['total' => $registration->balanceDue(), 'saving' => 0.0];
        }

        $people = $registration->attendees->map(fn ($a) => ['is_guest' => $a->is_guest, 'ticket_tier_id' => $a->ticket_tier_id, 'attending_dining' => $a->attending_dining])->all();
        $quote = $this->pricing->quote($registration->event, $people, $registration->promo_code, $option, true);

        return ['total' => $quote['total'], 'saving' => max(0.0, round((float) $registration->total - $quote['total'], 2))];
    }

    /**
     * Every online way of paying this booking now, each with what it would cost.
     *
     * @return list<array{id: int, label: string, type: string, total: float, saving: float}>
     */
    public function offers(EventRegistration $registration): array
    {
        if (! $this->canPay($registration)) {
            return [];
        }

        return $this->options($registration)->map(function (EventPaymentMethod $option) use ($registration) {
            $offer = $this->onlineOffer($registration, $option);

            return ['id' => $option->id, 'label' => $option->method->label, 'type' => $option->method->type, 'total' => $offer['total'], 'saving' => $offer['saving']];
        })->all();
    }

    /**
     * Create the payment page for what is still owed, on Stripe or PayPal, and return its address.
     * PayPal sends the payer back to $paypalReturnUrl to take the payment.
 for what is still owed and return its address.
     */
    public function start(EventRegistration $registration, string $successUrl, string $cancelUrl, ?int $optionId = null, ?string $paypalReturnUrl = null): string
    {
        $registration->loadMissing(['event.club', 'attendees', 'paymentMethod']);

        if (! $this->canPay($registration)) {
            throw ValidationException::withMessages(['payment' => 'This booking cannot be paid online.']);
        }

        $option = $this->optionFor($registration, $optionId);

        if (! $option) {
            throw ValidationException::withMessages(['payment' => 'That way of paying is not available for this booking.']);
        }

        // Choosing to pay online now moves the booking to the online price.
        if ($registration->payment_method_id !== $option->method->id && (float) $registration->amount_paid <= 0) {
            $registration = $this->registrations->reprice($registration, $option);
        }

        $club = $registration->event->club;
        $balance = $registration->balanceDue();

        if ($balance <= 0) {
            throw ValidationException::withMessages(['payment' => 'There is nothing left to pay on this booking.']);
        }

        if ($option->method->type === ClubPaymentMethod::PAYPAL) {
            if (! $paypalReturnUrl) {
                throw ValidationException::withMessages(['payment' => 'This booking cannot be paid with PayPal from here.']);
            }

            $order = $this->paypal->createOrder($option->method, [
                'amount' => $balance,
                'currency' => $club->currencyCode(),
                'reference' => (string) $registration->payment_reference,
                'custom_id' => (string) $registration->id,
                'description' => 'Booking: '.$registration->event->title.' ('.$registration->payment_reference.')',
                'brand' => $club->name,
                'return_url' => $paypalReturnUrl,
                'cancel_url' => $cancelUrl,
            ]);

            $registration->update(['paypal_order_id' => $order['id']]);

            return $order['url'];
        }

        $session = $this->stripe->createCheckoutSession($option->method, [
            'mode' => 'payment',
            'client_reference_id' => (string) $registration->id,
            'customer_email' => $registration->contact_email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($club->currencyCode()),
                    'unit_amount' => (int) round($balance * 100),
                    'product_data' => ['name' => 'Booking: '.$registration->event->title, 'description' => $club->name.' · reference '.$registration->payment_reference],
                ],
            ]],
            'metadata' => ['registration_id' => (string) $registration->id, 'event_id' => (string) $registration->event_id, 'club_id' => (string) $club->id],
            'payment_intent_data' => ['metadata' => ['registration_id' => (string) $registration->id, 'reference' => (string) $registration->payment_reference]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $registration->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    /**
     * Handle a notification from Stripe. Returns a short reason when nothing was done, null when a payment was recorded.
     *
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function handleWebhook(Club $club, string $payload, string $signature): ?string
    {
        $secrets = ClubPaymentMethod::where('club_id', $club->id)->where('type', ClubPaymentMethod::CARD)->get()
            ->map(fn (ClubPaymentMethod $m) => $m->config['stripe_webhook_secret'] ?? null)->filter()->unique();

        if ($secrets->isEmpty()) {
            throw new UnexpectedValueException('No Stripe webhook secret is set for this club.');
        }

        // The signature proves it came from Stripe for this lodge's account; a wrong one throws.
        $event = null;
        $failure = null;

        foreach ($secrets as $secret) {
            try {
                $event = Webhook::constructEvent($payload, $signature, $secret);
                break;
            } catch (SignatureVerificationException $e) {
                $failure = $e;
            }
        }

        if (! $event) {
            throw $failure ?? new UnexpectedValueException('Invalid Stripe event.');
        }

        if (! in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return 'ignored event type';
        }

        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            return 'not paid yet';
        }

        $registration = EventRegistration::with(['event.club', 'paymentMethod'])->find((int) ($session->metadata->registration_id ?? 0));

        // The booking must belong to this club and to this very Checkout session, so a forged or replayed event does nothing.
        if (! $registration || $registration->event->club_id !== $club->id || $registration->stripe_session_id !== $session->id) {
            Log::warning('Stripe payment did not match a booking', ['club' => $club->id, 'session' => $session->id ?? null]);

            return 'no matching booking';
        }

        $externalId = (string) ($session->payment_intent ?? $session->id);

        if (EventPaymentLog::where('external_id', $externalId)->exists()) {
            return 'already recorded';
        }

        $paid = round(((int) $session->amount_total) / 100, 2);

        if ($paid <= 0 || $paid > $registration->balanceDue() + 0.01) {
            Log::warning('Stripe payment amount does not fit the booking', ['registration' => $registration->id, 'paid' => $paid, 'owed' => $registration->balanceDue()]);

            return 'amount mismatch';
        }

        $this->payments->markPaid($registration, null, $paid, 'Card (Stripe)', now(), 'Paid online through Stripe', 'stripe_webhook', $externalId, $session->payment_intent ? (string) $session->payment_intent : null);

        return null;
    }

    /**
     * The payer came back from PayPal having approved the order: take the payment. Only the order this booking started can be captured.
     */
    public function capturePayPal(EventRegistration $registration, string $orderId): bool
    {
        $registration->loadMissing(['event.club', 'paymentMethod']);

        if (! $registration->paypal_order_id || ! hash_equals($registration->paypal_order_id, $orderId)) {
            return false;
        }

        $method = $this->options($registration)->map->method->first(fn (ClubPaymentMethod $m) => $m->type === ClubPaymentMethod::PAYPAL && $m->id === $registration->payment_method_id)
            ?? $this->options($registration)->map->method->first(fn (ClubPaymentMethod $m) => $m->type === ClubPaymentMethod::PAYPAL);

        if (! $method) {
            return false;
        }

        if ($registration->balanceDue() <= 0) {
            return true;
        }

        return $this->recordPayPalCapture($registration, $this->paypal->captureOrder($method, $orderId)) === null;
    }

    /**
     * Handle a notification from PayPal. Returns a short reason when nothing was done, null when a payment was recorded.
     *
     * @param  array<string, string>  $headers  lower-case request headers
     *
     * @throws UnexpectedValueException
     */
    public function handlePayPalWebhook(Club $club, string $payload, array $headers): ?string
    {
        $methods = ClubPaymentMethod::where('club_id', $club->id)->where('type', ClubPaymentMethod::PAYPAL)->get()->filter->isReadyForPayPal();

        if ($methods->isEmpty()) {
            throw new UnexpectedValueException('No PayPal webhook is set for this club.');
        }

        // PayPal itself confirms the notification is genuine for this lodge's webhook.
        if (! $methods->contains(fn (ClubPaymentMethod $m) => $this->paypal->verifyWebhook($m, $headers, $payload))) {
            throw new UnexpectedValueException('Invalid PayPal event.');
        }

        $event = json_decode($payload, true);

        if (($event['event_type'] ?? null) !== 'PAYMENT.CAPTURE.COMPLETED') {
            return 'ignored event type';
        }

        $resource = (array) ($event['resource'] ?? []);
        $registration = EventRegistration::with(['event.club', 'paymentMethod'])->find((int) ($resource['custom_id'] ?? 0));
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! $registration || $registration->event->club_id !== $club->id || ! $orderId || $registration->paypal_order_id !== $orderId) {
            Log::warning('PayPal payment did not match a booking', ['club' => $club->id, 'capture' => $resource['id'] ?? null]);

            return 'no matching booking';
        }

        return $this->recordPayPalCapture($registration, [
            'status' => (string) ($resource['status'] ?? ''),
            'capture_id' => isset($resource['id']) ? (string) $resource['id'] : null,
            'amount' => (float) ($resource['amount']['value'] ?? 0),
            'currency' => $resource['amount']['currency_code'] ?? null,
            'custom_id' => $resource['custom_id'] ?? null,
        ]);
    }

    /**
     * Record a completed PayPal capture once, if it is for this booking, in this currency and no more than is owed.
     *
     * @param  array{status: string, capture_id: ?string, amount: float, currency: ?string, custom_id: ?string}  $capture
     */
    private function recordPayPalCapture(EventRegistration $registration, array $capture): ?string
    {
        if ($capture['status'] !== 'COMPLETED' || ! $capture['capture_id']) {
            return 'not paid yet';
        }

        if ($capture['custom_id'] !== (string) $registration->id || strtoupper((string) $capture['currency']) !== strtoupper($registration->event->club->currencyCode())) {
            Log::warning('PayPal capture did not match the booking', ['registration' => $registration->id, 'capture' => $capture['capture_id']]);

            return 'no matching booking';
        }

        if (EventPaymentLog::where('external_id', $capture['capture_id'])->exists()) {
            return 'already recorded';
        }

        $paid = round($capture['amount'], 2);

        if ($paid <= 0 || $paid > $registration->balanceDue() + 0.01) {
            Log::warning('PayPal payment amount does not fit the booking', ['registration' => $registration->id, 'paid' => $paid, 'owed' => $registration->balanceDue()]);

            return 'amount mismatch';
        }

        try {
            $this->payments->markPaid($registration, null, $paid, 'PayPal', now(), 'Paid online through PayPal', 'paypal', $capture['capture_id']);
        } catch (UniqueConstraintViolationException) {
            return 'already recorded';
        }

        $registration->update(['paypal_capture_id' => $capture['capture_id']]);

        return null;
    }
}
