<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the NEW address when someone asks to sign in with it; the sign-in email only changes once the link is opened */
class ConfirmNewEmail extends Notification
{
    public function __construct(private readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your new email address')
            ->line('You asked to sign in to '.config('app.name').' with this address.')
            ->action('Confirm this address', $this->url)
            ->line('The link works for 24 hours. If you did not ask for this, ignore this email: nothing changes.');
    }
}
