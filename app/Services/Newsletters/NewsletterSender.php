<?php

namespace App\Services\Newsletters;

use App\Jobs\SendNewsletterDelivery;
use App\Jobs\SendNewsletterJob;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts sending a newsletter and keeps a record of who it went to. Only a draft can be sent, and each address is
 * sent to once, so a retry or a second click can never email anyone twice.
 */
class NewsletterSender
{
    public function __construct(private NewsletterAudience $audience) {}

    /**
     * Mark a draft as sent and queue the emails on the bulk queue.
     */
    public function send(Newsletter $newsletter): void
    {
        if ($newsletter->status === 'sent') {
            throw ValidationException::withMessages(['status' => 'This newsletter has already been sent. Duplicate it to send something new.']);
        }

        $newsletter->update(['status' => 'sent', 'sent_at' => now()]);

        SendNewsletterJob::dispatch($newsletter->id);
    }

    /**
     * Work out who to send to, record a delivery for each person once, and queue their emails.
     */
    public function queueDeliveries(Newsletter $newsletter): int
    {
        $existing = $newsletter->deliveries()->pluck('email')->map(fn ($email) => strtolower($email))->all();
        $queued = 0;

        foreach ($this->audience->recipients($newsletter) as $person) {
            if (in_array(strtolower($person['email']), $existing, true)) {
                continue;
            }

            $delivery = NewsletterDelivery::create([
                'newsletter_id' => $newsletter->id,
                'club_id' => $newsletter->club_id,
                'email' => $person['email'],
                'name' => $person['name'],
                'user_id' => $person['user_id'],
                'subscription_id' => $person['subscription_id'],
                'token' => Str::random(40),
            ]);

            SendNewsletterDelivery::dispatch($delivery->id);
            $queued++;
        }

        return $queued;
    }

    /**
     * Send again to the people whose email failed, and only them.
     */
    public function retryFailed(Newsletter $newsletter): int
    {
        $failed = $newsletter->deliveries()->where('status', NewsletterDelivery::FAILED)->get();

        foreach ($failed as $delivery) {
            $delivery->update(['status' => NewsletterDelivery::QUEUED, 'error' => null]);
            SendNewsletterDelivery::dispatch($delivery->id);
        }

        return $failed->count();
    }

    /**
     * @return array{queued: int, sent: int, failed: int}
     */
    public function stats(Newsletter $newsletter): array
    {
        $counts = $newsletter->deliveries()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['queued' => (int) ($counts[NewsletterDelivery::QUEUED] ?? 0), 'sent' => (int) ($counts[NewsletterDelivery::SENT] ?? 0), 'failed' => (int) ($counts[NewsletterDelivery::FAILED] ?? 0)];
    }
}
