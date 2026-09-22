<?php

namespace App\Console\Commands;

use App\Services\RecurringBillService;
use Illuminate\Console\Command;

class GenerateRecurringBillsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-recurring-bills';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate vendor bills from due recurring bill templates, across every club';

    /**
     * Execute the console command.
     */
    public function handle(RecurringBillService $recurringBillService): int
    {
        $result = $recurringBillService->generateDueBills();

        $this->info("Generated {$result['created_count']} recurring bill(s) totalling {$result['total_billed']}.");

        return self::SUCCESS;
    }
}
