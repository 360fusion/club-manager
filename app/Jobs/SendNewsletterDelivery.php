<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\NewsletterDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one newsletter to one person and records the outcome. Retried a few times if the mail service hiccups;
 * if it still fails the delivery is marked failed so an organiser can retry just those.
 */
class SendNewsletterDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('bulk');
    }

    /**
     * Keeps sending under the mail provider's per-second limit when one is set (NEWSLETTER_PER_SECOND).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return config('newsletters.per_second') > 0 ? [new RateLimited('newsletter')] : [];
    }

    public function handle(): void
    {
        $delivery = NewsletterDelivery::with('newsletter.club', 'newsletter.newsletterType')->find($this->deliveryId);

        if (! $delivery || $delivery->status === NewsletterDelivery::SENT) {
            return;
        }

        Mail::to($delivery->email)->send(NewsletterMail::forDelivery($delivery));

        $delivery->update(['status' => NewsletterDelivery::SENT, 'sent_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        NewsletterDelivery::whereKey($this->deliveryId)->update(['status' => NewsletterDelivery::FAILED, 'error' => mb_substr($exception->getMessage(), 0, 500)]);
    }
}
