<?php

namespace App\Mail;

use App\Models\EventRegistration;
use App\Services\Events\EventPayload;
use App\Support\Currencies;
use App\Support\EmailTemplate;
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

    /**
     * The editable superadmin template, filled in for this booking; null if it has been removed.
     *
     * @return array{subject: string, body: string}|null
     */
    private function template(): ?array
    {
        $event = $this->registration->event;
        $payment = EventPayload::payment($this->registration);

        return EmailTemplate::render('event_payment_reminder', [
            'club_name' => $event->club->name,
            'contact_name' => $this->registration->contact_name,
            'event_title' => $event->title,
            'event_day' => $event->starts_at?->format('l j F Y'),
            'amount_due' => Currencies::symbolFor($event->club).number_format((float) $payment['balance'], 2),
            'due_text' => $payment['due_at'] ? ', due by '.$payment['due_at'] : '',
        ], [
            'pay_link' => $this->registration->user_id && $payment['online']['can_pay']
                ? '<p><a href="'.e(route('member.events', ['slug' => $event->club->slug])).'">Pay online now</a></p>'
                : '',
            'payment_how' => view('emails.partials.event-pay-how', ['payment' => $payment, 'showBank' => true])->render(),
        ]);
    }

    public function envelope(): Envelope
    {
        $club = $this->registration->event->club;

        return new Envelope(
            subject: $this->template()['subject'] ?? 'Payment reminder: '.$this->registration->event->title,
            from: new Address(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name),
            replyTo: ! empty($club->settings['email_reply_to']) ? [new Address($club->settings['email_reply_to'], $club->name)] : [],
        );
    }

    public function content(): Content
    {
        if ($template = $this->template()) {
            return new Content(html: 'emails.templated', with: ['bodyHtml' => $template['body']]);
        }

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
