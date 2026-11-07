<?php

namespace App\Observers;

use App\Models\Event;
use App\Support\ClubTeamEvents;

/** An event published: made, or moved out of draft. Later status changes (completed, cancelled) are not this event */
class EventTeamEvents
{
    public function created(Event $event): void
    {
        $event->status !== 'draft' && $this->record($event);
    }

    public function updated(Event $event): void
    {
        $event->wasChanged('status') && $event->getOriginal('status') === 'draft' && $this->record($event);
    }

    private function record(Event $event): void
    {
        ClubTeamEvents::record((int) $event->club_id, 'event.published', "published the event {$event->title}", [
            'event_id' => $event->id, 'title' => $event->title, 'starts_at' => $event->starts_at?->toIso8601String(),
        ]);
    }
}
