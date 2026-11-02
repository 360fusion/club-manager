<?php

namespace App\Domains\ClubAccounting\Mail;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

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
        public ?string $pdfCacheKey = null,
        public ?string $pdfFilename = null,
    ) {
        $this->emailSubject = $this->customSubject;
        $this->attachPdf = $this->pdfCacheKey !== null;
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

    /**
     * The PDF is not carried in the queued job (raw bytes cannot be JSON encoded): it is read from the cache, where the
     * sender put it once for everyone, or built again if that copy has gone.
     */
    public function attachments(): array
    {
        if ($this->pdfCacheKey === null) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => base64_decode(Cache::get($this->pdfCacheKey) ?? base64_encode(app(CommitteePackCompilerService::class)->compilePdf($this->meeting))),
                $this->pdfFilename ?: 'Agenda-Pack.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
