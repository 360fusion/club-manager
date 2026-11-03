<?php

namespace App\Console\Commands;

use App\Support\BillingChecks;
use Illuminate\Console\Command;

class BillingDoctorCommand extends Command
{
    protected $signature = 'billing:doctor';

    protected $description = 'Check the Stripe keys, webhook secret, plan prices and live-site settings';

    public function handle(): int
    {
        $checks = BillingChecks::run();

        $this->table(['Check', 'OK', 'Detail'], array_map(fn ($c) => [$c['name'], $c['ok'] ? 'yes' : 'NO', $c['detail']], $checks));

        return collect($checks)->every(fn ($c) => $c['ok'] || ! $c['enforced']) ? self::SUCCESS : self::FAILURE;
    }
}
