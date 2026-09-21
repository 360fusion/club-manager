<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\EventRegistration;
use App\Support\Currencies;

/**
 * Tells a booker that money has been refunded on their booking.
 */
class EventRefundMail extends EventTemplatedMail
{
    public function __construct(public EventRegistration $registration, public float $amount)
    {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->registration->event->club;
    }

    protected function templateKey(): string
    {
        return 'event_refund';
    }

    protected function values(): array
    {
        $event = $this->registration->event;

        return [
            'text' => [
                'club_name' => $event->club->name,
                'contact_name' => $this->registration->contact_name,
                'event_title' => $event->title,
                'event_date' => $event->starts_at?->format('l j F Y, H:i'),
                'amount_refunded' => Currencies::symbolFor($event->club).number_format($this->amount, 2),
                'reference' => $this->registration->payment_reference,
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        return 'Refund: '.$this->registration->event->title;
    }

    protected function fallbackBody(): string
    {
        $values = $this->values()['text'];

        return '<h2>Refund</h2><p>We have refunded <strong>'.e($values['amount_refunded']).'</strong> for <strong>'.e($values['event_title']).'</strong>. It can take a few days to reach you.</p>';
    }
}
