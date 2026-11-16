<?php

namespace Launchpad\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\MediaFile;
use Launchpad\Models\MediaFolder;
use Launchpad\Models\MediaTag;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceFile;
use Launchpad\Webhooks\Webhooks;
use Launchpad\Workspaces\Activity;
use Launchpad\Workspaces\WorkspaceScope;
use RuntimeException;

/** A team's media library: saving uploads (type, size, the team's space), folders, tags and the trash */
final class Library
{
    /** names the library's own views use, so no folder may take them (compared in lower case) */
    public const RESERVED = ['all', 'all files', 'all media', 'trash', 'no folder', 'none', 'unfiled'];

    public function __construct(private readonly Entitlements $entitlements) {}

    public static function disk(): string
    {
        return (string) config('launchpad.media.disk', 'local');
    }

    /** @return list<string> the validation rules for one uploaded file; the type is read from the content, not taken from the name */
    public static function rules(): array
    {
        return ['required', 'file', 'max:'.(int) config('launchpad.media.max_kb', 51200), 'mimes:'.implode(',', (array) config('launchpad.media.mimes'))];
    }

    /** @return array<string, string> plain words for a refused upload, keyed for a field called `file` */
    public static function messages(): array
    {
        return [
            'file.mimes' => __("That kind of file can't be added here. These can: :values."),
            'file.max' => __('That file is too big: the most is :size.', ['size' => Number::fileSize((int) config('launchpad.media.max_kb', 51200) * 1024, precision: 0)]),
        ];
    }

    /** What a file chooser offers for one kind of file (image/*, video/*, audio/*), or every allowed type */
    public static function accept(?string $kind = null): string
    {
        return in_array($kind, ['image', 'video', 'audio'], true) ? "{$kind}/*" : '.'.implode(',.', (array) config('launchpad.media.mimes'));
    }

    /** @return int|null bytes, or null for no limit */
    public function quota(Workspace $workspace): ?int
    {
        $mb = $this->entitlements->limitOr($workspace, 'storage_mb', (int) config('launchpad.media.quota_mb', 100));

        return $mb >= Entitlements::UNLIMITED ? null : $mb * 1024 * 1024;
    }

    /** Media bytes (the trash too, until it is emptied) and, with the files module on, the Files page's bytes: one space for both */
    public function used(Workspace $workspace): int
    {
        $used = (int) $this->files($workspace)->withTrashed()->sum('size');

        if (config('launchpad.modules.files')) {
            $used += (int) WorkspaceFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->sum('size');
        }

        return $used;
    }

    /** @throws ValidationException when it would take the team over its space */
    public function store(Workspace $workspace, UploadedFile $file, ?MediaFolder $folder = null, ?Model $by = null): MediaFile
    {
        $this->sameTeam($workspace, $folder, 'folder_id');
        $quota = $this->quota($workspace);

        if ($quota !== null && $this->used($workspace) + $file->getSize() > $quota) {
            throw ValidationException::withMessages(['file' => __('This team has run out of space. Delete something or upgrade the plan.')]);
        }

        // read before the file is moved
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $name = $this->fileName($file->getClientOriginalName(), 'file');
        $size = (int) $file->getSize();
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs("media/{$workspace->id}", Str::random(40), ['disk' => self::disk()]) ?: throw new RuntimeException('The file could not be saved.');

        $stored = MediaFile::create([
            'workspace_id' => $workspace->id, 'folder_id' => $folder?->id, 'uploaded_by' => $by?->getKey(), 'disk' => self::disk(), 'path' => $path,
            'name' => $name, 'name_key' => $this->key($name), 'mime' => $mime, 'kind' => MediaFile::kindOf($mime), 'size' => $size, 'hash' => $hash,
        ]);
        $this->tell($stored, 'file.uploaded');

        return $stored;
    }

    /** A live file of the team with this content, if any (the trash does not count) */
    public function duplicateOf(Workspace $workspace, string $hash): ?MediaFile
    {
        return $this->files($workspace)->where('hash', $hash)->first();
    }

    /** @throws ValidationException */
    public function makeFolder(Workspace $workspace, string $name, ?MediaFolder $parent, ?Model $by = null): MediaFolder
    {
        $this->sameTeam($workspace, $parent, 'parent_id');
        $name = $this->folderName($name);

        return DB::transaction(function () use ($workspace, $name, $parent, $by) {
            $this->lock($workspace->id);
            $this->assertFree($workspace->id, $name, $parent?->id);

            return MediaFolder::create([
                'workspace_id' => $workspace->id, 'parent_id' => $parent?->id, 'path' => $this->pathBelow($parent), 'name' => $name,
                'name_key' => $this->key($name), 'created_by' => $by?->getKey(),
            ]);
        });
    }

    /** @throws ValidationException */
    public function renameFolder(MediaFolder $folder, string $name): void
    {
        $name = $this->folderName($name);

        DB::transaction(function () use ($folder, $name) {
            $this->lock($folder->workspace_id);
            $this->assertFree($folder->workspace_id, $name, $folder->parent_id, $folder->id);
            $folder->update(['name' => $name, 'name_key' => $this->key($name)]);
        });
    }

    /** @throws ValidationException when the folder would go inside itself, or its new place already has one by that name */
    public function moveFolder(MediaFolder $folder, ?MediaFolder $parent): void
    {
        DB::transaction(function () use ($folder, $parent) {
            $this->lock($folder->workspace_id);
            $folder->refresh();
            $parent?->refresh();
            $this->sameTeam($folder->workspace, $parent, 'parent_id');

            if ($parent && ($parent->id === $folder->id || str_contains($parent->path, "/{$folder->id}/"))) {
                throw ValidationException::withMessages(['parent_id' => __("A folder can't go inside itself.")]);
            }

            $this->assertFree($folder->workspace_id, $folder->name, $parent?->id, $folder->id, $folder->name_key);
            $this->relocate($folder, $parent);
        });
    }

    /** @throws ValidationException when the folder is another team's */
    public function moveFile(MediaFile $file, ?MediaFolder $folder): void
    {
        $this->sameTeam($file->workspace, $folder, 'folder_id');
        $file->update(['folder_id' => $folder?->id]);
    }

    /**
     * @param  array{name?: string, alt?: ?string, notes?: ?string, tags?: list<string>}  $data
     *
     * @throws ValidationException
     */
    public function update(MediaFile $file, array $data): void
    {
        $changes = [];

        if (array_key_exists('name', $data)) {
            $changes['name'] = $this->fileName((string) $data['name']);
            $changes['name_key'] = $this->key($changes['name']);
        }
        foreach (['alt' => 500, 'notes' => 5000] as $field => $max) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = trim((string) $data[$field]) === '' ? null : Str::limit(trim((string) $data[$field]), $max, '');
            }
        }

        DB::transaction(function () use ($file, $data, $changes) {
            $file->update($changes);

            if (array_key_exists('tags', $data)) {
                $file->tags()->sync(collect($data['tags'])->map(fn ($tag) => $this->tag($file->workspace_id, (string) $tag))->filter()->pluck('id')->unique()->all());
            }
        });
    }

    /** A file goes to the trash on its own; a folder takes what is inside it (not already trashed) under one batch, so restoring it brings the same back */
    public function trash(MediaFile|MediaFolder $item): void
    {
        if ($item instanceof MediaFile) {
            // on its own: no batch, so restoring a folder never brings it back
            $item->delete();
            $item->update(['trash_batch' => null]);
            $this->tell($item, 'file.deleted');

            return;
        }

        DB::transaction(function () use ($item) {
            $batch = (string) Str::uuid();
            $folders = $this->branch($item)->pluck('id')->push($item->id);
            $gone = ['deleted_at' => now(), 'trash_batch' => $batch];

            MediaFile::withoutGlobalScope(WorkspaceScope::class)->whereIn('folder_id', $folders)->update($gone);
            MediaFolder::withoutGlobalScope(WorkspaceScope::class)->whereIn('id', $folders)->update($gone);
            $item->refresh();
        });

        Activity::record($item->workspace_id, 'folder.deleted', "deleted the folder {$item->name}");
        $this->tell($item, 'folder.deleted');
    }

    public function restore(MediaFile|MediaFolder $item): void
    {
        if ($item instanceof MediaFile) {
            // its folder may be trashed, or gone
            $home = $item->folder_id && MediaFolder::withoutGlobalScope(WorkspaceScope::class)->whereKey($item->folder_id)->exists();
            $item->restore();
            $item->update(['trash_batch' => null] + ($home ? [] : ['folder_id' => null]));
            $this->tell($item, 'file.restored');

            return;
        }

        DB::transaction(function () use ($item) {
            $this->lock($item->workspace_id);
            $item->refresh();

            $root = $item;

            if ($item->trash_batch) {
                $same = fn (Builder $query) => $query->where('workspace_id', $item->workspace_id)->where('trash_batch', $item->trash_batch);
                $back = ['deleted_at' => null, 'trash_batch' => null];
                $members = $same(MediaFolder::withoutGlobalScope(WorkspaceScope::class)->onlyTrashed())->get();
                // the checks below belong to the folder that was trashed, whichever member was clicked: the one whose parent is not in the batch
                $root = $members->first(fn (MediaFolder $folder) => ! $members->contains('id', $folder->parent_id)) ?? $item;

                $same(MediaFile::withoutGlobalScope(WorkspaceScope::class)->onlyTrashed())->update($back);
                $same(MediaFolder::withoutGlobalScope(WorkspaceScope::class)->onlyTrashed())->update($back);
            } else {
                $item->restore();
            }

            $item->refresh();
            $root->refresh();
            $parent = $root->parent_id ? MediaFolder::withoutGlobalScope(WorkspaceScope::class)->find($root->parent_id) : null;
            $name = $root->name;

            // each try is built from the original name, so a long one still changes and the loop ends
            for ($try = 1; $this->taken($root->workspace_id, $this->key($name), $parent?->id, $root->id); $try++) {
                if ($try > 100) {
                    throw ValidationException::withMessages(['name' => __('There are too many folders with this name here.')]);
                }
                $tail = $try === 1 ? ' (restored)' : " (restored {$try})";
                $name = Str::limit($root->name, 100 - mb_strlen($tail), '').$tail;
            }

            $root->update(['name' => $name, 'name_key' => $this->key($name)]);
            // the parent may be trashed (or gone): the folder comes back at the top
            $root->parent_id && ! $parent && $this->relocate($root, null);
            $item->refresh();
        });
    }

    /** Whether the folder or a folder below it (trashed or not) holds a file added by anyone but this person */
    public function holdsOthersFiles(MediaFolder $folder, mixed $userId): bool
    {
        $folders = $this->branch($folder, true)->pluck('id')->push($folder->id);

        return MediaFile::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->whereIn('folder_id', $folders)
            ->where(fn (Builder $query) => $query->whereNull('uploaded_by')->orWhere('uploaded_by', '!=', $userId))->exists();
    }

    /** Deleted for good, bytes included; a folder takes what is inside it, the trash too */
    public function destroy(MediaFile|MediaFolder $item): void
    {
        if ($item instanceof MediaFile) {
            DB::transaction(function () use ($item) {
                $item->tags()->detach();
                $item->forceDelete();
            });

            return;
        }

        DB::transaction(function () use ($item) {
            $folders = $this->branch($item, true)->pluck('id')->push($item->id);

            MediaFile::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->whereIn('folder_id', $folders)->get()->each(fn (MediaFile $file) => $this->destroy($file));
            MediaFolder::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->whereIn('id', $folders)->forceDelete();
        });
    }

    /** Every file, folder and tag of a team that is going away, bytes included (a database cascade would leave the bytes on the disk) */
    public function purge(Workspace $workspace): void
    {
        $this->files($workspace)->withTrashed()->get()->each(fn (MediaFile $file) => $this->destroy($file));
        MediaFolder::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->where('workspace_id', $workspace->id)->forceDelete();
        MediaTag::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->delete();
    }

    /** Tells the team's webhook endpoints, once the surrounding transaction (if any) has committed */
    private function tell(MediaFile|MediaFolder $item, string $event): void
    {
        $payload = ['id' => $item->id, 'name' => $item->name, 'folder_id' => $item instanceof MediaFile ? $item->folder_id : $item->parent_id];

        DB::afterCommit(fn () => Webhooks::dispatch($item->workspace, $event, $payload));
    }

    private function files(Workspace $workspace): Builder
    {
        return MediaFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id);
    }

    /** The lower-cased name two siblings are compared by */
    private function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** without `/`, backslashes and control characters, as `Files::store` does */
    private function fileName(string $name, ?string $fallback = null): string
    {
        $name = trim(Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $name) ?? '', 200, ''));

        if ($name === '' && $fallback === null) {
            throw ValidationException::withMessages(['name' => __('Give the file a name of up to 200 letters.')]);
        }

        return $name === '' ? $fallback : $name;
    }

    /** @throws ValidationException when it is empty, too long, or kept for the library's own views */
    private function folderName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $name) ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => __('Give the folder a name of up to 100 letters.')]);
        }
        if (in_array($this->key($name), self::RESERVED, true)) {
            throw ValidationException::withMessages(['name' => __('That name is kept for the library. Choose another.')]);
        }

        return $name;
    }

    private function sameTeam(Workspace $workspace, ?MediaFolder $folder, string $field): void
    {
        if ($folder && $folder->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages([$field => __('That folder is not in this team.')]);
        }
    }

    /** Folder writes of one team run one after another, so two at once cannot both pass the name check */
    private function lock(int $workspaceId): void
    {
        Workspace::query()->whereKey($workspaceId)->lockForUpdate()->value('id');
    }

    private function taken(int $workspaceId, string $key, ?int $parentId, ?int $except = null): bool
    {
        return MediaFolder::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspaceId)->where('parent_id', $parentId)
            ->where('name_key', $key)->when($except, fn ($query) => $query->whereKeyNot($except))->exists();
    }

    /** @throws ValidationException when a live folder in that place has the name */
    private function assertFree(int $workspaceId, string $name, ?int $parentId, ?int $except = null, ?string $key = null): void
    {
        if ($this->taken($workspaceId, $key ?? $this->key($name), $parentId, $except)) {
            throw ValidationException::withMessages(['name' => __('There is already a folder called :name here.', ['name' => $name])]);
        }
    }

    /** the ids of the folders above a folder inside this one, as /1/5/ */
    private function pathBelow(?MediaFolder $parent): string
    {
        return $parent ? $this->within($parent) : '';
    }

    /** the path of what is directly inside a folder */
    private function within(MediaFolder $folder): string
    {
        return ($folder->path ?: '/').$folder->id.'/';
    }

    /** Every live folder below this one (the trash too, with $trashed) */
    private function branch(MediaFolder $folder, bool $trashed = false): Builder
    {
        return MediaFolder::withoutGlobalScope(WorkspaceScope::class)->when($trashed, fn ($query) => $query->withTrashed())->where('workspace_id', $folder->workspace_id)->where('path', 'like', $this->within($folder).'%');
    }

    /** Puts a folder under another (or at the top) and rewrites the path of everything below it, the trash included */
    private function relocate(MediaFolder $folder, ?MediaFolder $parent): void
    {
        $old = $this->within($folder);
        $new = ($this->pathBelow($parent) ?: '/').$folder->id.'/';

        MediaFolder::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->where('workspace_id', $folder->workspace_id)->where('path', 'like', $old.'%')->get()
            ->each(fn (MediaFolder $below) => $below->update(['path' => $new.substr($below->path, strlen($old))]));
        $folder->update(['parent_id' => $parent?->id, 'path' => $this->pathBelow($parent)]);
    }

    private function tag(int $workspaceId, string $name): ?MediaTag
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '');

        if ($name === '') {
            return null;
        }
        $name = Str::limit($name, 100, '');

        return MediaTag::withoutGlobalScope(WorkspaceScope::class)->firstOrCreate(['workspace_id' => $workspaceId, 'name_key' => $this->key($name)], ['name' => $name]);
    }
}
