<?php

namespace App\Mail;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Club $club,
        public string $senderName,
        public string $senderEmail,
        public ?string $senderPhone,
        public string $messageContent
    ) {}

    public function envelope(): Envelope
    {
        $fromName = $this->club->name . ' Website Contact Form';

        return new Envelope(
            subject: "[Contact Inquiry] Message from {$this->senderName} - {$this->club->name}",
            from: new Address(config('mail.from.address'), $fromName),
            replyTo: [new Address($this->senderEmail, $this->senderName)]
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-form-submitted',
            with: [
                'club' => $this->club,
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'senderPhone' => $this->senderPhone,
                'messageContent' => $this->messageContent,
            ]
        );
    }
}
