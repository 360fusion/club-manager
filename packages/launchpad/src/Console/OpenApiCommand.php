<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Launchpad\Api\OpenApi;
use RuntimeException;

/** `php artisan launchpad:openapi` merges the API description, says what is wrong with it, and with --output writes it as JSON (exit 1 on a problem) */
class OpenApiCommand extends Command
{
    protected $signature = 'launchpad:openapi {--output= : Write the merged description to this file}';

    protected $description = 'Check (and optionally write) the API description';

    public function handle(): int
    {
        try {
            $spec = OpenApi::build();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($problems = OpenApi::problems($spec)) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        if ($output = $this->option('output')) {
            file_put_contents($output, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info("Written to {$output}.");
        }

        $this->info(count((array) $spec['paths']).' path(s) from '.count(OpenApi::files()).' file(s), no problems.');

        return self::SUCCESS;
    }
}
