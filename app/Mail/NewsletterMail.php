<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * A club newsletter to one person, in the club's branded layout, with their own one-click unsubscribe link.
 * The queue job that sends it runs on the bulk queue, so this is a plain mailable.
 */
class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Newsletter $newsletter,
        public string $recipientName,
        public ?string $unsubscribeUrl,
        public ?string $viewUrl,
        public bool $isTest = false,
        public ?string $subjectOverride = null,
        public ?string $contentOverride = null,
    ) {}

    public static function forDelivery(NewsletterDelivery $delivery): self
    {
        return new self(
            $delivery->newsletter,
            (string) $delivery->name,
            route('newsletters.unsubscribe', ['token' => $delivery->token]),
            route('newsletters.view', ['token' => $delivery->token]),
        );
    }

    /**
     * "Dear {{first_name}}" style tags, filled in for this person and escaped.
     */
    public static function mergeTags(string $text, string $recipientName, Club $club): string
    {
        $name = trim($recipientName);
        $first = $name !== '' ? explode(' ', $name)[0] : 'there';

        return strtr($text, [
            '{{first_name}}' => e($first),
            '{{name}}' => e($name !== '' ? $name : 'there'),
            '{{club_name}}' => e($club->name),
        ]);
    }

    private function club(): Club
    {
        return $this->newsletter->club;
    }

    public function envelope(): Envelope
    {
        $club = $this->club();
        $type = $this->newsletter->newsletterType;
        $subject = html_entity_decode(strip_tags(self::mergeTags($this->subjectOverride ?? $this->newsletter->subject, $this->recipientName, $club)));
        $replyTo = $type?->sender_email ?: ($club->settings['email_reply_to'] ?? null);

        return new Envelope(
            subject: ($this->isTest ? '[Test] ' : '').trim(str_replace(["\r", "\n"], ' ', $subject)),
            from: new Address(config('mail.from.address'), $type?->sender_name ?: ($club->settings['email_from_name'] ?? $club->name)),
            replyTo: $replyTo ? [new Address($replyTo, $club->name)] : [],
        );
    }

    public function headers(): Headers
    {
        if (! $this->unsubscribeUrl || $this->isTest) {
            return new Headers;
        }

        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.newsletter', with: $this->viewData());
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(bool $web = false): array
    {
        $club = $this->club();
        $colour = (string) ($club->settings['primary_color'] ?? '');
        $absolute = fn (?string $path) => $path && str_starts_with($path, '/') ? url($path) : $path;

        return [
            'club' => $club,
            'newsletter' => $this->newsletter,
            'body' => self::mergeTags($this->contentOverride ?? (string) $this->newsletter->content, $this->recipientName, $club),
            'colour' => preg_match('/^#[0-9a-fA-F]{6}$/', $colour) ? $colour : '#4f46e5',
            'logo' => $absolute($club->logo_url),
            'attachments' => collect($this->newsletter->attachments ?? [])->map(fn ($file) => ['name' => $file['name'] ?? 'Download', 'url' => $absolute($file['url'] ?? null)])->filter(fn ($file) => $file['url'])->values()->all(),
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'viewUrl' => $web ? null : $this->viewUrl,
            'web' => $web,
            'isTest' => $this->isTest,
        ];
    }
}
