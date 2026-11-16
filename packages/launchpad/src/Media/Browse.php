<?php

namespace Launchpad\Media;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Launchpad\Models\MediaFile;
use Launchpad\Models\MediaFolder;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\WorkspaceScope;

/**
 * What a team's media library shows: the folders and one page of files for a search, filters and a sort.
 * Stage 2's access scope (which files and folders a member may see) must reach every read, not only `Browse::for`:
 * `MediaController::tree()`, the folder lookup and breadcrumbs of the browse call, `Library::duplicateOf` (check),
 * show, update and trash by id, and the tags list.
 */
class Browse
{
    public const PER_PAGE = 50;

    public const MAX_PER_PAGE = 100;

    /**
     * Filters: folder (id, null is the top), everywhere (search the folder and its sub-folders, or the whole library with no folder),
     * q (name or notes), kind, tag (name), month (YYYY-MM), trashed (the trash, whatever the folder), sort (newest, oldest, name, size), per_page.
     *
     * @param  array<string, mixed>  $filters
     * @return array{folders: Collection<int, MediaFolder>, files: LengthAwarePaginator}
     */
    public static function for(Workspace $workspace, array $filters): array
    {
        $trashed = (bool) ($filters['trashed'] ?? false);
        $folder = $trashed || empty($filters['folder']) ? null : MediaFolder::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->findOrFail((int) $filters['folder']);
        $everywhere = (bool) ($filters['everywhere'] ?? false);
        $q = trim((string) ($filters['q'] ?? ''));
        $like = $q === '' ? null : '%'.self::escape(mb_strtolower($q)).'%';
        // a type filter keeps folders (a picker for images always has one); a tag or month is a hunt for files
        $onlyFiles = ! empty($filters['tag']) || ! empty($filters['month']);

        // the team is named here, so this is safe outside a request too
        $files = MediaFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->with(['tags', 'uploader']);
        $folders = MediaFolder::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id);

        if ($trashed) {
            $files->onlyTrashed();
            $folders->onlyTrashed();
        } elseif (! $everywhere) {
            $files->where('folder_id', $folder?->id);
            $folders->where('parent_id', $folder?->id);
        } elseif ($folder) {
            $below = self::escape(($folder->path ?: '/').$folder->id.'/').'%';
            $files->whereIn('folder_id', MediaFolder::withoutGlobalScope(WorkspaceScope::class)->select('id')->where('workspace_id', $workspace->id)
                ->where(fn ($b) => $b->where('id', $folder->id)->orWhereRaw("path like ? escape '!'", [$below])));
            $folders->whereRaw("path like ? escape '!'", [$below]);
        }

        $like && $files->where(fn ($w) => $w->whereRaw("lower(name) like ? escape '!'", [$like])->orWhereRaw("lower(notes) like ? escape '!'", [$like]));
        ! empty($filters['kind']) && $files->where('kind', $filters['kind']);
        ! empty($filters['tag']) && $files->whereHas('tags', fn ($t) => $t->where('name_key', mb_strtolower(trim((string) $filters['tag']))));
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) ($filters['month'] ?? ''), $m)) {
            $from = now()->setDate((int) $m[1], (int) $m[2], 1)->startOfDay();
            $files->where('created_at', '>=', $from)->where('created_at', '<', $from->copy()->addMonth());
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $files->orderBy('created_at')->orderBy('id'),
            'name' => $files->orderBy('name_key')->orderBy('id'),
            'size' => $files->orderByDesc('size')->orderByDesc('id'),
            default => $files->orderByDesc('created_at')->orderByDesc('id'),
        };

        // folders are listed by name; a search narrows them to the ones that match
        $like && $folders->whereRaw("lower(name) like ? escape '!'", [$like]);

        return [
            'folders' => $onlyFiles ? new Collection : $folders->orderBy('name_key')->get(),
            'files' => $files->paginate(min(max((int) ($filters['per_page'] ?? self::PER_PAGE), 1), self::MAX_PER_PAGE)),
        ];
    }

    /** @return list<array{id: int, name: string}> the folders from the top to this one, in order */
    public static function breadcrumbs(?MediaFolder $folder): array
    {
        if (! $folder) {
            return [];
        }

        return MediaFolder::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $folder->workspace_id)->whereKey(array_filter(explode('/', $folder->path)))->get()
            ->sortBy(fn ($f) => strlen($f->path))->push($folder)->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values()->all();
    }

    /** `!` is the escape character: it means the same in a LIKE on SQLite, MySQL and Postgres, which a backslash does not */
    private static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
