<?php

namespace App\Observers;

use App\Models\Newsletter;
use App\Support\ClubTeamEvents;

/** A newsletter sent (NewsletterSender marks it sent once its deliveries are queued) */
class NewsletterTeamEvents
{
    public function created(Newsletter $newsletter): void
    {
        $newsletter->status === 'sent' && $this->record($newsletter);
    }

    public function updated(Newsletter $newsletter): void
    {
        $newsletter->wasChanged('status') && $newsletter->status === 'sent' && $this->record($newsletter);
    }

    private function record(Newsletter $newsletter): void
    {
        ClubTeamEvents::record((int) $newsletter->club_id, 'newsletter.sent', "sent the newsletter {$newsletter->subject}", [
            'newsletter_id' => $newsletter->id, 'subject' => $newsletter->subject,
        ]);
    }
}
