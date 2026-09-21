<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a guest whose address the booker gave: their place, their meal and who booked them. It never
 * carries the booker's payment details or their private booking link.
 */
class EventGuestBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public EventRegistration $registration, public EventAttendee $guest) {}

    /**
     * The editable superadmin template. It is only ever given what a guest may see: no payment details and no private booking link.
     *
     * @return array{subject: string, body: string}|null
     */
    private function template(): ?array
    {
        $waiting = $this->registration->status === 'waitlisted';
        $event = $this->event;
        $meal = array_filter($this->guest->mealSummary());
        $bookedBy = (string) $this->registration->contact_name;

        return EmailTemplate::render('event_guest_confirmation', [
            'heading' => $waiting ? 'You are on the waiting list' : 'You are booked in',
            'guest_status_line' => $waiting ? 'On the waiting list' : 'You are booked in',
            'guest_name' => $this->guest->name,
            'booked_by' => $bookedBy,
            'booking_verb' => $waiting ? 'asked for' : 'booked',
            'club_name' => $event->club->name,
            'event_title' => $event->title,
            'event_date' => $event->starts_at?->format('l j F Y, H:i'),
            'event_location' => $event->formatted_location,
        ], [
            'waiting_note' => $waiting ? '<p>The event is currently full, so you are on the waiting list. We will email '.e($bookedBy).' if a place becomes available.</p>' : '',
            'meal_choices' => $meal ? '<p style="margin-bottom:4px;"><strong>Your meal</strong></p><p style="margin-top:0;">'.e(implode(' · ', $meal)).'</p>' : '',
            'dietary_note' => $this->guest->dietary_requirements ? '<p><strong>Dietary needs noted:</strong> '.e($this->guest->dietary_requirements).'</p>' : '',
            'cancellation_policy' => $event->cancellation_policy ? '<p style="color:#475569"><small>'.e($event->cancellation_policy).'</small></p>' : '',
        ], $event->club);
    }

    public function envelope(): Envelope
    {
        $club = $this->event->club;
        $waiting = $this->registration->status === 'waitlisted';

        return new Envelope(
            subject: $this->template()['subject'] ?? ($waiting ? 'On the waiting list: ' : 'You are booked in: ').$this->event->title,
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
            html: 'emails.event-guest-booking',
            with: [
                'event' => $this->event,
                'club' => $this->event->club,
                'guest' => $this->guest,
                'bookedBy' => $this->registration->contact_name,
                'waiting' => $this->registration->status === 'waitlisted',
                'meal' => array_filter($this->guest->mealSummary()),
            ],
        );
    }
}
