<?php

namespace App\Mail;

use App\Models\PassedOnDelivery;
use App\Support\PassedOnPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * One person's email of a passed-on item. The PDF is not attached, so a big circular to a whole lodge stays light:
 * the email links to the item page (no sign-in needed), with a one-click unsubscribe from this kind.
 */
class PassedOnMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public PassedOnDelivery $delivery)
    {
        $this->onQueue('bulk');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: trim(str_replace(["\r", "\n"], ' ', $this->delivery->item->title)));
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.route('passed_on.unsubscribe', ['token' => $this->delivery->token]).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $item = $this->delivery->item;

        return new Content(html: 'emails.passed-on', with: [
            'item' => $item,
            'kindLabel' => PassedOnPreferences::LABELS[$this->delivery->kind] ?? '',
            'openUrl' => route('passed_on.public', ['token' => $this->delivery->token]),
            'choicesUrl' => route('passed_on.public', ['token' => $this->delivery->token]).'#choices',
        ]);
    }
}
