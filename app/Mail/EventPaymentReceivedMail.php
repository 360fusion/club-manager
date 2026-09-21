<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\EventRegistration;
use App\Support\Currencies;

/**
 * A receipt for a payment recorded against a booking.
 */
class EventPaymentReceivedMail extends EventTemplatedMail
{
    public function __construct(public EventRegistration $registration, public float $amount) {}

    protected function club(): Club
    {
        return $this->registration->event->club;
    }

    protected function templateKey(): string
    {
        return 'event_payment_received';
    }

    protected function values(): array
    {
        $event = $this->registration->event;
        $balance = $this->registration->balanceDue();

        return [
            'text' => [
                'club_name' => $event->club->name,
                'contact_name' => $this->registration->contact_name,
                'event_title' => $event->title,
                'event_date' => $event->starts_at?->format('l j F Y, H:i'),
                'amount_received' => $this->money($this->amount),
                'total' => $this->money((float) $this->registration->total),
                'payment_status' => $balance > 0 ? 'Part paid' : 'Paid in full',
                'balance_text' => $balance > 0 ? ' Still to pay: '.$this->money($balance).'.' : '',
                'reference' => $this->registration->payment_reference,
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        return 'Payment received: '.$this->registration->event->title;
    }

    protected function fallbackBody(): string
    {
        $values = $this->values()['text'];

        return '<h2>Payment received</h2><p>Thank you, we have received <strong>'.e($values['amount_received']).'</strong> for <strong>'.e($values['event_title']).'</strong>. '.e($values['payment_status']).'.'.e($values['balance_text']).'</p>';
    }

    private function money(float $amount): string
    {
        return Currencies::symbolFor($this->club()).number_format($amount, 2);
    }
}
