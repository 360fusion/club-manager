<?php

namespace App\Services\Events;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\EventPaymentLog;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Services\Payment\StripeGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Paying a booking by card on Stripe's own page, using the lodge's own Stripe account, and hearing back
 * from Stripe when the payment is made. The amount always comes from the booking, never from the browser.
 */
class EventOnlinePayment
{
    public function __construct(private StripeGateway $stripe, private EventRegistrationService $registrations, private EventPaymentService $payments, private EventPricing $pricing) {}

    /**
     * The online option a booking could be paid with, if the event offers one and it is ready.
     */
    public function optionFor(EventRegistration $registration): ?EventPaymentMethod
    {
        if (! config('events.online_payments')) {
            return null;
        }

        return $this->pricing->enabledMethods($registration->event)->first(fn (EventPaymentMethod $o) => $o->method->type === ClubPaymentMethod::CARD);
    }

    public function canPay(EventRegistration $registration): bool
    {
        return $registration->status === 'attending'
            && ! in_array($registration->payment_status, ['paid', 'waived', 'refunded'], true)
            && (float) $registration->total > 0
            && ! in_array($registration->event->status, ['draft', 'cancelled', 'completed'], true)
            && $this->optionFor($registration) !== null;
    }

    /**
     * What the booking would cost if paid online now (the online price), for "pay now and save".
     *
     * @return array{total: float, saving: float}|null
     */
    public function onlineOffer(EventRegistration $registration): ?array
    {
        $option = $this->canPay($registration) ? $this->optionFor($registration) : null;

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
     * Create the Stripe Checkout page for what is still owed and return its address.
     */
    public function start(EventRegistration $registration, string $successUrl, string $cancelUrl): string
    {
        $registration->loadMissing(['event.club', 'attendees', 'paymentMethod']);

        if (! $this->canPay($registration)) {
            throw ValidationException::withMessages(['payment' => 'This booking cannot be paid online.']);
        }

        $option = $this->optionFor($registration);

        // Choosing to pay online now moves the booking to the online price.
        if ($registration->payment_method_id !== $option->method->id && (float) $registration->amount_paid <= 0) {
            $registration = $this->registrations->reprice($registration, $option);
        }

        $club = $registration->event->club;
        $balance = $registration->balanceDue();

        if ($balance <= 0) {
            throw ValidationException::withMessages(['payment' => 'There is nothing left to pay on this booking.']);
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
}
