<?php

namespace Launchpad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Mail\Renderer;
use Launchpad\Models\ContactMessage;

/** To the sender: we have your message, here is its reference. */
class ContactReceipt extends Notification implements ShouldQueue
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
        return Renderer::message('contact.receipt', ['reference' => $this->message->reference, 'name' => $this->message->name, 'message' => $this->message->message]);
    }
}
