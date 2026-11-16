<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Launchpad\Models\SyncEvent;
use Launchpad\Sync\Sync;

class SyncCommand extends Command
{
    protected $signature = 'launchpad:sync {--status : Only say what is waiting}';

    protected $description = 'Deliver the changes waiting for the other service';

    public function handle(Sync $sync): int
    {
        if (! $sync->enabled()) {
            $this->warn('The sync module is off (launchpad.modules.sync).');

            return self::SUCCESS;
        }

        if (! $this->option('status')) {
            $this->info($sync->deliver().' delivered.');
        }

        $this->line(SyncEvent::where('status', 'pending')->count().' waiting, '.SyncEvent::where('status', 'failed')->count().' gave up.');

        return SyncEvent::where('status', 'failed')->exists() ? self::FAILURE : self::SUCCESS;
    }
}
