<?php

namespace App\Console\Commands;

use App\Support\HealthChecks;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'app:doctor {--strict : Fail on every problem, not only when APP_ENV is production}';

    protected $description = 'Check that this install is set up to be live (debug off, mail, queue worker, migrations, secrets) and say what to fix';

    public function handle(): int
    {
        $checks = HealthChecks::run();
        $enforced = $this->option('strict') || app()->isProduction();

        $this->table(['Check', 'Result', 'Detail'], array_map(fn ($c) => [$c['name'], $c['ok'] ? 'ok' : ($enforced ? 'FAIL' : 'warn'), $c['detail']], $checks));

        $problems = count(array_filter($checks, fn ($c) => ! $c['ok']));

        if ($problems === 0) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        if (! $enforced) {
            $this->warn("{$problems} thing(s) to fix before going live. Not enforced here: run with --strict, or on production, to fail on them.");

            return self::SUCCESS;
        }

        $this->error("{$problems} check(s) failed.");

        return self::FAILURE;
    }
}
