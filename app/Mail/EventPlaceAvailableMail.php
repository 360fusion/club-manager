<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\EventRegistration;

/**
 * Tells a booker on the waiting list that a place has opened and they are now booked in.
 */
class EventPlaceAvailableMail extends EventTemplatedMail
{
    public function __construct(public EventRegistration $registration)
    {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->registration->event->club;
    }

    protected function templateKey(): string
    {
        return 'event_place_available';
    }

    protected function values(): array
    {
        $event = $this->registration->event;
        $payLink = $this->registration->user_id && (float) $this->registration->total > 0
            ? '<p><a href="'.e(route('member.events', ['slug' => $event->club->slug])).'">View your booking and pay</a></p>'
            : '';

        return [
            'text' => [
                'club_name' => $event->club->name,
                'contact_name' => $this->registration->contact_name,
                'event_title' => $event->title,
                'event_date' => $event->starts_at?->format('l j F Y, H:i'),
                'event_location' => $event->formatted_location,
                'people' => (string) $this->registration->attendees->count(),
            ],
            'html' => ['pay_link' => $payLink],
        ];
    }

    protected function fallbackSubject(): string
    {
        return 'A place is available: '.$this->registration->event->title;
    }

    protected function fallbackBody(): string
    {
        return '<h2>You are booked in</h2><p>A place has opened up for <strong>'.e($this->registration->event->title).'</strong>, so your booking is now confirmed.</p>';
    }
}
