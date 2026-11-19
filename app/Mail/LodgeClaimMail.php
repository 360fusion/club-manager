<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A short email with one button: about a lodge claim (to the superadmins or the person who made it), a member who changed
 * their details (to the Secretary), and an automatic website build or push that failed (StaticSiteBuilder::tellWebsiteEditors).
 */
class LodgeClaimMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** A mail server that is briefly down gets three tries, a minute and then five minutes apart. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

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
