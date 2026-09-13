<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Club $club,
        public User $user,
        public string $invitationToken,
        public string $acceptUrl
    ) {}

    public function envelope(): Envelope
    {
        $fromName = $this->club->settings['email_from_name'] ?? $this->club->name;
        $replyTo = $this->club->settings['email_reply_to'] ?? null;

        $envelope = new Envelope(
            subject: "You're invited to join {$this->club->name}!",
            from: new Address(config('mail.from.address'), $fromName)
        );

        if ($replyTo) {
            $envelope->replyTo = [new Address($replyTo, $fromName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.member-invitation',
        );
    }
}
