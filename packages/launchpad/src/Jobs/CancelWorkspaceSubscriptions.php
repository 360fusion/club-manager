<?php

namespace Launchpad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Launchpad\Billing\Gateway;
use Launchpad\Models\Workspace;

/** Ends what a closed account's workspace was paying for. It is tried again, with a wait, for a day: a Stripe outage must not leave a closed account billed. */
class CancelWorkspaceSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public Workspace $workspace) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200, 21600, 43200];
    }

    public function handle(Gateway $gateway): void
    {
        $gateway->cancelSubscriptions($this->workspace);
    }
}
