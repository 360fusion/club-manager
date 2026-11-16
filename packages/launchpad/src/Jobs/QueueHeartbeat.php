<?php

namespace Launchpad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Launchpad\Health\Heartbeat;

/** Sent through the queue every minute by the scheduler: when a worker has run it, the queue is being worked */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Heartbeat::beat('queue');
    }
}
