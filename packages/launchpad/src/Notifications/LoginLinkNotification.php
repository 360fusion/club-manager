<?php

namespace Launchpad\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Accounts\LoginLinks;
use Launchpad\Mail\Renderer;

/** The sign-in link (sent at once, not queued: the person is waiting for it) */
class LoginLinkNotification extends Notification
{
    public function __construct(public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Renderer::message('account.login_link', ['name' => $notifiable->name ?? '', 'url' => $this->url, 'minutes' => (string) LoginLinks::MINUTES]);
    }
}
