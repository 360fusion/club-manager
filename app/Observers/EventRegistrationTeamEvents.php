<?php

namespace App\Observers;

use App\Models\EventRegistration;
use App\Support\ClubTeamEvents;

/** A booking made, and a booking paid in full */
class EventRegistrationTeamEvents
{
    public function created(EventRegistration $registration): void
    {
        $this->record($registration, 'event.registration.created', 'recorded a booking by :name for :event', ['total' => $registration->total]);
        $registration->payment_status === 'paid' && $this->paid($registration);
    }

    public function updated(EventRegistration $registration): void
    {
        $registration->wasChanged('payment_status') && $registration->payment_status === 'paid' && $this->paid($registration);
    }

    private function paid(EventRegistration $registration): void
    {
        $this->record($registration, 'event.registration.paid', 'recorded the booking by :name for :event as paid', ['amount_paid' => $registration->amount_paid]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function record(EventRegistration $registration, string $name, string $sentence, array $extra): void
    {
        $event = $registration->event;

        if (! $event) {
            return;
        }

        ClubTeamEvents::record((int) $event->club_id, $name, strtr($sentence, [':name' => $registration->contact_name ?? 'someone', ':event' => $event->title]), [
            'registration_id' => $registration->id, 'event_id' => $event->id, 'contact_name' => $registration->contact_name,
        ] + $extra);
    }
}
