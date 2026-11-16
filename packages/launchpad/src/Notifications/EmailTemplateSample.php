<?php

namespace Launchpad\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Mail\Renderer;
use Launchpad\Mail\Templates;

/** The admin's "Send me a test": a template with its sample values, sent at once to the staff member (unsaved wording can be tried) */
class EmailTemplateSample extends Notification
{
    /** @param  array{subject: string, body: string}|null  $wording */
    public function __construct(private string $key, private ?array $wording = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = Renderer::message($this->key, Templates::get($this->key)['sample'], $this->wording);
        $message->subject('[Test] '.$message->subject);

        return $message;
    }
}
