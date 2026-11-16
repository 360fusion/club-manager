<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Launchpad\Accounts\AccountClosure;

class PurgeClosedAccountsCommand extends Command
{
    protected $signature = 'launchpad:purge-closed {--dry-run : Only say which accounts are due}';

    protected $description = 'Remove accounts that have been closed for longer than the retention period';

    public function handle(AccountClosure $closure): int
    {
        $due = $closure->due();
        $this->info($due->count().' closed account(s) are past their '.$closure->retentionDays().' days.');

        foreach ($due as $user) {
            if ($this->option('dry-run')) {
                $this->line("would remove #{$user->getKey()} {$user->email}");

                continue;
            }

            try {
                $closure->purge($user);
                $this->line("removed #{$user->getKey()}");
            } catch (\Throwable $e) {
                // one account that cannot be removed does not stop the others; it is tried again tomorrow
                report($e);
                $this->error("could not remove #{$user->getKey()}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
