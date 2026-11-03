<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccountClosure;
use App\Support\AccountPurging;
use Illuminate\Console\Command;

class PurgeClosedAccountsCommand extends Command
{
    protected $signature = 'accounts:purge-closed';

    protected $description = 'Blank accounts whose owners asked to close them more than 30 days ago';

    public function handle(): int
    {
        $purged = 0;

        User::whereNotNull('closure_requested_at')->whereNull('purged_at')
            ->where('closure_requested_at', '<=', now()->subDays(AccountClosure::WAIT_DAYS))
            ->each(function (User $user) use (&$purged) {
                // Something may have changed in 30 days (they became a club's only owner): leave it for a person to look at.
                if ($blockers = AccountClosure::blockers($user)) {
                    $this->warn("Skipped user {$user->id}: ".implode(' ', $blockers));

                    return;
                }

                AccountPurging::purge($user);
                $purged++;
            });

        $this->info("Closed {$purged} account(s).");

        return self::SUCCESS;
    }
}
