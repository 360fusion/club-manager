<?php

namespace App\Mail;

use App\Models\Club;
use App\Support\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An event email whose wording the superadmin edits as a template, sent from the lodge's own name.
 * If the template has been removed, the plain built-in wording is used instead.
 */
abstract class EventTemplatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    abstract protected function club(): Club;

    abstract protected function templateKey(): string;

    /**
     * @return array{text: array<string, scalar|null>, html: array<string, string>}
     */
    abstract protected function values(): array;

    abstract protected function fallbackSubject(): string;

    /**
     * The built-in body, as HTML with any values already escaped.
     */
    abstract protected function fallbackBody(): string;

    /**
     * @return array{subject: string, body: string}
     */
    private function filled(): array
    {
        $values = $this->values();

        return EmailTemplate::render($this->templateKey(), $values['text'], $values['html'], $this->club())
            ?? ['subject' => $this->fallbackSubject(), 'body' => $this->fallbackBody()];
    }

    public function envelope(): Envelope
    {
        $club = $this->club();

        return new Envelope(
            subject: $this->filled()['subject'],
            from: new Address(config('mail.from.address'), $club->settings['email_from_name'] ?? $club->name),
            replyTo: ! empty($club->settings['email_reply_to']) ? [new Address($club->settings['email_reply_to'], $club->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(html: 'emails.templated', with: ['bodyHtml' => $this->filled()['body']]);
    }
}
