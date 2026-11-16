<?php

namespace Launchpad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Mail\Renderer;

/** "There is room for you now": the link to sign up, for someone on the waitlist */
class WaitlistInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url, public string $name = '') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Renderer::message('account.waitlist_invite', ['name' => $this->name, 'url' => $this->url]);
    }
}
