<?php

namespace Launchpad\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Launchpad\Media\Library;
use Launchpad\Models\MediaFile;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceFile;
use Launchpad\Workspaces\Activity;
use Launchpad\Workspaces\WorkspaceScope;

class MediaImportFilesCommand extends Command
{
    protected $signature = 'launchpad:media-import-files {--team= : Only this team (its slug)}';

    protected $description = "Move the Files page's files into the media library";

    public function handle(): int
    {
        if (! config('launchpad.modules.media')) {
            $this->error('Turn on launchpad.modules.media and run the migrations first.');

            return self::FAILURE;
        }

        $files = WorkspaceFile::withoutGlobalScope(WorkspaceScope::class);

        if ($slug = $this->option('team')) {
            $team = Workspace::where('slug', $slug)->first();

            if (! $team) {
                $this->error("No team has the slug {$slug}.");

                return self::FAILURE;
            }
            $files->where('workspace_id', $team->id);
        }

        [$moved, $skipped, $failed] = [0, 0, 0];

        // moving a file is not an upload or a delete: nothing goes in the team's activity or to its webhooks
        Activity::silently(function () use ($files, &$moved, &$skipped, &$failed) {
            $files->chunkById(100, function ($chunk) use (&$moved, &$skipped, &$failed) {
                foreach ($chunk as $old) {
                    if (MediaFile::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->where('legacy_file_id', $old->id)->exists()) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $this->move($old);
                        $moved++;
                    } catch (\Throwable $e) {
                        // the old row and bytes stay; the rest go on, and a later run tries this one again
                        report($e);
                        $this->error("could not move #{$old->id} {$old->name}: {$e->getMessage()}");
                        $failed++;
                    }
                }
            });
        });

        $this->info("Moved {$moved} files ({$skipped} skipped, {$failed} failed)");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function move(WorkspaceFile $old): void
    {
        $bytes = Storage::disk($old->disk)->get($old->path);

        if ($bytes === null) {
            throw new \RuntimeException('its bytes are missing');
        }

        $disk = Library::disk();
        $path = "media/{$old->workspace_id}/".Str::random(40);
        Storage::disk($disk)->put($path, $bytes);
        $committed = false;

        try {
            if (! Storage::disk($disk)->exists($path) || Storage::disk($disk)->size($path) !== strlen($bytes) || strlen($bytes) !== (int) $old->size) {
                throw new \RuntimeException('the copy does not match');
            }

            DB::transaction(function () use ($old, $disk, $path, $bytes) {
                MediaFile::create([
                    'workspace_id' => $old->workspace_id, 'folder_id' => null, 'uploaded_by' => $old->uploaded_by, 'disk' => $disk, 'path' => $path,
                    'name' => $old->name, 'name_key' => mb_strtolower(trim($old->name)), 'mime' => $old->mime, 'kind' => MediaFile::kindOf($old->mime),
                    'size' => $old->size, 'hash' => hash('sha256', $bytes), 'legacy_file_id' => $old->id, 'created_at' => $old->created_at,
                ]);
                // quietly: its deleted hook would take the old bytes before the commit
                $old->deleteQuietly();
            });
            $committed = true;
        } catch (\Throwable $e) {
            // before the commit the old row and bytes are all still there; the copy is the only thing to clean up
            $committed || Storage::disk($disk)->delete($path);

            throw $e;
        }

        // the new row is saved: a failure here only leaves the old bytes on the disk, and the file counts as moved
        try {
            Storage::disk($old->disk)->delete($old->path);
        } catch (\Throwable $e) {
            report($e);
            $this->warn("moved #{$old->id} {$old->name}, but its old bytes could not be deleted: {$old->disk}:{$old->path}");
        }
    }
}
