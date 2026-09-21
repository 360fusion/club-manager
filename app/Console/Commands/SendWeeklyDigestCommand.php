<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\NewsletterType;
use App\Services\WeeklyUpdateDigestService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendWeeklyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-weekly-digest {--club= : Specific club slug to run for} {--force : Force send even if no approved updates exist} {--scheduled : Only clubs whose digest channel is due now (day, hour and frequency)}';

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

        if ($this->option('scheduled')) {
            $now = Carbon::now();
            $dueClubIds = NewsletterType::where('is_automated_digest', true)->get()->filter(fn (NewsletterType $type) => $digestService->isDue($type, $now))->pluck('club_id')->unique();
            $clubsQuery->whereIn('id', $dueClubIds);
        }

        $clubs = $clubsQuery->get();

        if ($clubs->isEmpty()) {
            if ($this->option('scheduled')) {
                return Command::SUCCESS;
            }

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
