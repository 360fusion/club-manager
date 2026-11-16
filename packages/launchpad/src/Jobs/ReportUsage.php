<?php

namespace Launchpad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Launchpad\Billing\Gateway;
use Launchpad\Models\Workspace;

/** Tells Stripe about use beyond what a plan includes. The identifier is made once, so a retry is not counted twice. */
class ReportUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $workspaceId, public string $event, public int $quantity, public string $identifier) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(Gateway $gateway): void
    {
        if ($workspace = Workspace::find($this->workspaceId)) {
            $gateway->reportUsage($workspace, $this->event, $this->quantity, $this->identifier);
        }
    }
}
