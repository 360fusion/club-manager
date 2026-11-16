<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Launchpad\Health\Checks;

/**
 * `php artisan launchpad:doctor` checks that the app is set up to take payments, and says what to fix. `--remote` also asks Stripe (prices,
 * webhook endpoint and its events, the customer portal), which needs the real keys. It exits with 1 if anything fails, so a deploy can stop on it.
 */
class DoctorCommand extends Command
{
    protected $signature = 'launchpad:doctor {--remote : Also check the Stripe account} {--running : Also check the scheduler and a queue worker are running (a few minutes after a deploy)}';

    protected $description = 'Check that billing is set up';

    public function handle(): int
    {
        $checks = Checks::run((bool) $this->option('remote'), (bool) $this->option('running'));

        $this->table(['Check', 'Result', 'Detail'], array_map(fn ($c) => [$c['name'], $c['ok'] ? 'ok' : 'FAIL', $c['detail']], $checks));

        $failed = count(array_filter($checks, fn ($c) => ! $c['ok']));
        $failed ? $this->error("{$failed} check(s) failed.") : $this->info('All checks passed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
