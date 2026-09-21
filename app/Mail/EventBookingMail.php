<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventPayload;
use App\Support\Currencies;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an outside guest after they book: the details and a private link to view or cancel.
 */
class EventBookingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
        public string $manageUrl,
    ) {
        $this->onQueue('transactional');
    }

    /**
     * The editable superadmin template, filled in for this booking; null if it has been removed.
     *
     * @return array{subject: string, body: string}|null
     */
    private function template(): ?array
    {
        $waiting = $this->registration->status === 'waitlisted';
        $event = $this->event;
        $club = $event->club;
        $registration = $this->registration->loadMissing(['attendees.starter', 'attendees.main', 'attendees.dessert']);
        $payment = EventPayload::payment($registration);

        return EmailTemplate::render('event_booking_confirmation', [
            'heading' => $waiting ? 'You are on the waiting list' : 'Thank you, you are booked in',
            'status_line' => $waiting ? 'You are on the waiting list' : 'Your booking',
            'club_name' => $club->name,
            'contact_name' => $registration->contact_name,
            'event_title' => $event->title,
            'event_date' => $event->starts_at?->format('l j F Y, H:i'),
            'event_location' => $event->formatted_location,
            'manage_url' => $this->manageUrl,
        ], [
            'waiting_note' => $waiting ? '<p>The event is currently full. We will email you if a place becomes available.</p>' : '',
            'booked_for' => view('emails.partials.event-attendees', ['registration' => $registration])->render(),
            'payment_details' => view('emails.partials.event-payment-summary', ['registration' => $registration, 'payment' => $payment, 'symbol' => Currencies::symbolFor($club)])->render(),
            'cancellation_policy' => $event->cancellation_policy ? '<p style="color:#475569"><small>'.e($event->cancellation_policy).'</small></p>' : '',
        ], $club);
    }

    public function envelope(): Envelope
    {
        $waiting = $this->registration->status === 'waitlisted';
        $club = $this->event->club;

        return new Envelope(
            subject: $this->template()['subject'] ?? ($waiting ? 'You are on the waiting list: ' : 'Your booking: ').$this->event->title,
            from: new Address(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name),
            replyTo: ! empty($club->settings['email_reply_to']) ? [new Address($club->settings['email_reply_to'], $club->name)] : [],
        );
    }

    public function content(): Content
    {
        if ($template = $this->template()) {
            return new Content(html: 'emails.templated', with: ['bodyHtml' => $template['body']]);
        }

        return new Content(
            html: 'emails.event-booking',
            with: [
                'event' => $this->event,
                'club' => $this->event->club,
                'registration' => $this->registration->loadMissing(['attendees.starter', 'attendees.main', 'attendees.dessert']),
                'manageUrl' => $this->manageUrl,
                'payment' => EventPayload::payment($this->registration),
                'symbol' => Currencies::symbolFor($this->event->club),
            ],
        );
    }
}
