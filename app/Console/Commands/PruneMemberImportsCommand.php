<?php

namespace App\Console\Commands;

use App\Domains\ClubAccounting\Models\MemberImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneMemberImportsCommand extends Command
{
    protected $signature = 'app:prune-member-imports {--stale-hours=24 : Discard unfinished imports older than this} {--keep-days=90 : Delete finished imports (and their row data) after this}';

    protected $description = 'Remove uploaded member spreadsheets that were never imported, and old import records, since they hold personal data';

    public function handle(): int
    {
        $discarded = 0;
        $deleted = 0;

        MemberImport::where('status', MemberImport::STAGED)
            ->where('updated_at', '<', now()->subHours((int) $this->option('stale-hours')))
            ->each(function (MemberImport $import) use (&$discarded) {
                if ($import->stored_path) {
                    Storage::disk('local')->delete($import->stored_path);
                }

                $import->rows()->delete();
                $import->update(['status' => MemberImport::CANCELLED, 'stored_path' => null]);
                $discarded++;
            });

        MemberImport::whereIn('status', [MemberImport::IMPORTED, MemberImport::UNDONE, MemberImport::CANCELLED])
            ->where('updated_at', '<', now()->subDays((int) $this->option('keep-days')))
            ->each(function (MemberImport $import) use (&$deleted) {
                if ($import->stored_path) {
                    Storage::disk('local')->delete($import->stored_path);
                }

                $import->delete();
                $deleted++;
            });

        $this->info("Discarded {$discarded} unfinished ".($discarded === 1 ? 'import' : 'imports').", deleted {$deleted} old ".($deleted === 1 ? 'record' : 'records').'.');

        return self::SUCCESS;
    }
}
