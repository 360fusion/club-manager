<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;

class PruneMediaCommand extends Command
{
    protected $signature = 'app:prune-media {--trash-days= : Delete files that have been in the Trash bin this long (default: Media::TRASH_DAYS)} {--keep-versions= : Keep this many earlier versions of each file (default: Media::KEEP_VERSIONS)}';

    protected $description = 'Delete files that have sat in the Trash bin too long, and trim old versions of replaced or cropped files';

    public function handle(): int
    {
        $trashDays = (int) ($this->option('trash-days') ?: Media::TRASH_DAYS);
        $keep = (int) ($this->option('keep-versions') ?: Media::KEEP_VERSIONS);

        $purged = 0;
        $trimmed = 0;

        Media::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($trashDays))
            ->each(function (Media $media) use (&$purged) {
                $media->forceDelete();
                $purged++;
            });

        Media::query()
            ->has('versions', '>', $keep)
            ->each(function (Media $media) use ($keep, &$trimmed) {
                $trimmed += $media->pruneVersions($keep);
            });

        $this->info("Deleted {$purged} ".($purged === 1 ? 'file' : 'files')." from the Trash bin, removed {$trimmed} old ".($trimmed === 1 ? 'version' : 'versions').'.');

        return self::SUCCESS;
    }
}
