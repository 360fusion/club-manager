<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an outside guest after they book: the details and a private link to view or cancel.
 */
class EventBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
        public string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        $waiting = $this->registration->status === 'waitlisted';
        $club = $this->event->club;

        return new Envelope(
            subject: ($waiting ? 'You are on the waiting list: ' : 'Your booking: ').$this->event->title,
            from: new Address(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name),
            replyTo: ! empty($club->settings['email_reply_to']) ? [new Address($club->settings['email_reply_to'], $club->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.event-booking',
            with: [
                'event' => $this->event,
                'club' => $this->event->club,
                'registration' => $this->registration->loadMissing(['attendees.starter', 'attendees.main', 'attendees.dessert']),
                'manageUrl' => $this->manageUrl,
            ],
        );
    }
}
