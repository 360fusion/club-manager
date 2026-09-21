<?php

namespace App\Mail;

use App\Models\EventRegistration;
use App\Services\Events\EventPayload;
use App\Support\Currencies;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A reminder that a booking still has something to pay, with how to pay it.
 */
class EventPaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EventRegistration $registration) {}

    public function envelope(): Envelope
    {
        $club = $this->registration->event->club;

        return new Envelope(
            subject: 'Payment reminder: '.$this->registration->event->title,
            from: new Address(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name),
            replyTo: ! empty($club->settings['email_reply_to']) ? [new Address($club->settings['email_reply_to'], $club->name)] : [],
        );
    }

    public function content(): Content
    {
        $event = $this->registration->event;

        return new Content(
            html: 'emails.event-payment-reminder',
            with: [
                'event' => $event,
                'club' => $event->club,
                'registration' => $this->registration,
                'payment' => EventPayload::payment($this->registration),
                'symbol' => Currencies::symbolFor($event->club),
            ],
        );
    }
}
