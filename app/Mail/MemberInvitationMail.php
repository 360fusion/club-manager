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
        $template = \App\Models\DefaultEmailTemplate::where('template_key', 'account_invitation')->first();

        if ($template) {
            $replacements = [
                '{{member_name}}' => e($this->user->name),
                '{{club_name}}' => e($this->club->name),
                '{{invite_url}}' => $this->acceptUrl,
                '{{expiry_days}}' => 7,
            ];
            $subject = str_replace(array_keys($replacements), array_values($replacements), $template->subject);
        } else {
            $isExistingUser = ! empty($this->user->password) && $this->user->clubs()->where('clubs.id', '!=', $this->club->id)->exists();
            $subject = $isExistingUser
                ? "Access granted to {$this->club->name} on Club Manager"
                : "You're invited to join {$this->club->name}!";
        }

        $envelope = new Envelope(
            subject: $subject,
            from: new Address(config('mail.from.address'), $fromName)
        );

        if ($replyTo) {
            $envelope->replyTo = [new Address($replyTo, $fromName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        $template = \App\Models\DefaultEmailTemplate::where('template_key', 'account_invitation')->first();

        if ($template) {
            $replacements = [
                '{{member_name}}' => e($this->user->name),
                '{{club_name}}' => e($this->club->name),
                '{{invite_url}}' => $this->acceptUrl,
                '{{expiry_days}}' => 7,
            ];
            $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $template->body_html);

            return new Content(
                htmlString: $bodyHtml
            );
        }

        $isExistingUser = ! empty($this->user->password) && $this->user->clubs()->where('clubs.id', '!=', $this->club->id)->exists();

        return new Content(
            html: 'emails.member-invitation',
            with: [
                'isExistingUser' => $isExistingUser,
            ]
        );
    }
}
