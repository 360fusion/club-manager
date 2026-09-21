<?php

namespace App\Services\Events;

use App\Mail\EventBookingMail;
use App\Mail\EventPaymentReceivedMail;
use App\Mail\EventPlaceAvailableMail;
use App\Mail\EventRefundMail;
use App\Models\EventRegistration;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the emails that follow a change to a booking. A mail problem is logged and never undoes the booking or payment.
 */
class EventMailer
{
    public function paymentReceived(EventRegistration $registration, float $amount): void
    {
        $this->send($registration, fn () => new EventPaymentReceivedMail($registration->fresh(['event.club', 'paymentMethod']), $amount));
    }

    public function refunded(EventRegistration $registration, float $amount): void
    {
        $this->send($registration, fn () => new EventRefundMail($registration->fresh(['event.club']), $amount));
    }

    public function placeOpened(EventRegistration $registration): void
    {
        $this->send($registration, fn () => new EventPlaceAvailableMail($registration->fresh(['event.club', 'attendees'])));
    }

    /**
     * The booking confirmation for a member, pointing at their own bookings page instead of a private link.
     */
    public function memberBooked(EventRegistration $registration): void
    {
        $this->send($registration, function () use ($registration) {
            $registration = $registration->fresh(['event.club', 'attendees']);

            return new EventBookingMail($registration->event, $registration, route('member.events', ['slug' => $registration->event->club->slug]));
        });
    }

    /**
     * @param  callable(): Mailable  $mail
     */
    private function send(EventRegistration $registration, callable $mail): void
    {
        if (! $registration->contact_email) {
            return;
        }

        try {
            Mail::to($registration->contact_email)->send($mail());
        } catch (Throwable $e) {
            Log::warning('Event email could not be sent', ['registration' => $registration->id, 'error' => $e->getMessage()]);
        }
    }
}
