<?php

namespace Launchpad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Mail\Renderer;
use Launchpad\Models\ContactMessage;

/** To the support address: a message from the contact form. Replying to the email goes to the sender. */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $message)
    {
        // sent to an address, not a person, and queued: it keeps the language of the request that made it
        $this->locale(app()->getLocale());
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->message;

        return Renderer::message('contact.received', ['reference' => $m->reference, 'name' => $m->name, 'email' => $m->email, 'topic' => $m->topic ?: __('Message'), 'message' => $m->message])
            ->replyTo($m->email, $m->name);
    }
}
