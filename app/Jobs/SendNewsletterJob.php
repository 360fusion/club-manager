<?php

namespace App\Jobs;

use App\Models\Newsletter;
use App\Services\Newsletters\NewsletterSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Works out who a sent newsletter goes to and queues one email each. Runs on the bulk queue, so a big newsletter
 * never holds up booking confirmations or payment receipts.
 */
class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $newsletterId)
    {
        $this->onQueue('bulk');
    }

    public function handle(NewsletterSender $sender): void
    {
        $newsletter = Newsletter::with(['club', 'newsletterType'])->find($this->newsletterId);

        if ($newsletter) {
            $sender->queueDeliveries($newsletter);
        }
    }
}
