<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A short email with one button: about a lodge claim (to the superadmins or the person who made it), and about an
 * automatic website build or push that failed (StaticSiteBuilder::tellWebsiteEditors).
 */
class LodgeClaimMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $heading,
        public string $body,
        public string $actionLabel,
        public string $actionUrl,
    ) {
        $this->onQueue('transactional');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.lodge-claim');
    }
}
