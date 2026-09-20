<?php

namespace App\Domains\ClubAccounting\Mail;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommitteeAgendaPackMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;

    public bool $attachPdf = true;

    public function __construct(
        public ClubCommitteeMeeting $meeting,
        public User $recipient,
        public string $customSubject,
        public string $customBodyHtml,
        public ?string $pdfBinary = null,
        public ?string $pdfFilename = null,
    ) {
        $this->emailSubject = $this->customSubject;
        $this->attachPdf = ! empty($this->pdfBinary);
    }

    public function envelope(): Envelope
    {
        $club = $this->meeting->club;
        $fromName = $club->settings['email_from_name'] ?? $club->name;
        $replyTo = $club->settings['email_reply_to'] ?? null;

        $envelope = new Envelope(
            subject: $this->customSubject,
            from: new Address(config('mail.from.address', 'noreply@clubmanager.test'), $fromName)
        );

        if ($replyTo) {
            $envelope->replyTo = [new Address($replyTo, $fromName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            html: 'club-accounting.emails.committee-agenda-pack',
            with: [
                'meeting' => $this->meeting,
                'recipient' => $this->recipient,
                'club' => $this->meeting->club,
                'bodyContent' => $this->customBodyHtml,
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->pdfBinary) {
            return [
                Attachment::fromData(fn () => $this->pdfBinary, $this->pdfFilename ?: 'Agenda-Pack.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
