<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubUpdate;
use App\Services\WeeklyUpdateDigestService;
use Illuminate\Console\Command;

class SendWeeklyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-weekly-digest {--club= : Specific club slug to run for} {--force : Force send even if no approved updates exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile and send weekly digest email for clubs';

    /**
     * Execute the console command.
     */
    public function handle(WeeklyUpdateDigestService $digestService): int
    {
        $clubSlug = $this->option('club');
        $force = $this->option('force');

        $clubsQuery = Club::query();
        if ($clubSlug) {
            $clubsQuery->where('slug', $clubSlug);
        }

        $clubs = $clubsQuery->get();

        if ($clubs->isEmpty()) {
            $this->error('No matching clubs found.');

            return Command::FAILURE;
        }

        $dispatchedCount = 0;

        foreach ($clubs as $club) {
            $approvedCount = ClubUpdate::where('club_id', $club->id)->where('status', 'approved')->count();

            if ($approvedCount === 0 && ! $force) {
                $this->info("Skipping {$club->name}: No approved updates waiting.");

                continue;
            }

            $this->info("Dispatching weekly digest for {$club->name} ({$approvedCount} approved updates)...");
            $newsletter = $digestService->dispatchWeeklyDigest($club);
            $dispatchedCount++;

            $this->info("✓ Sent Newsletter #{$newsletter->id} for {$club->name}.");
        }

        $this->info("Completed. Dispatched weekly digest for {$dispatchedCount} club(s).");

        return Command::SUCCESS;
    }
}
