<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\PageRevision;
use App\Services\PagePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrunePageRevisionsCommand extends Command
{
    protected $signature = 'app:prune-page-revisions';

    protected $description = 'Trim the website builder\'s page history to each lodge\'s limits: the newest N versions per page, and nothing older than its age limit';

    public function handle(): int
    {
        $removed = 0;

        Club::query()->whereIn('id', PageRevision::query()->select('club_id'))->each(function (Club $club) use (&$removed) {
            $keep = PagePublisher::keepFor($club);
            $days = PagePublisher::maxAgeDaysFor($club);

            // Pages holding more versions than the limit (a lodge may have lowered it since they were made).
            $pageIds = PageRevision::where('club_id', $club->id)->select('page_id')->groupBy('page_id')->havingRaw('count(*) > ?', [$keep])->pluck('page_id');

            foreach ($pageIds as $pageId) {
                $ids = PageRevision::where('page_id', $pageId)->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->pluck('id');
                $removed += PageRevision::whereIn('id', $ids)->delete();
            }

            if ($days > 0) {
                // Old versions go, but the newest version of every page is always kept so there is something to go back to.
                $newest = PageRevision::where('club_id', $club->id)->select(DB::raw('max(id)'))->groupBy('page_id');

                $removed += PageRevision::where('club_id', $club->id)
                    ->where('created_at', '<', now()->subDays($days))
                    ->whereNotIn('id', $newest)
                    ->delete();
            }
        });

        $this->info("Removed {$removed} old page ".($removed === 1 ? 'version' : 'versions').'.');

        return self::SUCCESS;
    }
}
