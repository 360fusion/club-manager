<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Launchpad\Models\HelpFeedback;

class PruneHelpFeedbackCommand extends Command
{
    protected $signature = 'help:prune-feedback';

    protected $description = 'Delete answers to "Was this helpful?" older than a year';

    public function handle(): int
    {
        $this->info(HelpFeedback::where('created_at', '<', now()->subDays(365))->delete().' old answer(s) removed.');

        return self::SUCCESS;
    }
}
