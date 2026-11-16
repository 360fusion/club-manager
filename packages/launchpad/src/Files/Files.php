<?php

namespace Launchpad\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\MediaFile;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceFile;
use Launchpad\Workspaces\WorkspaceScope;

/** Saving a workspace's uploads: what may be sent (type, size) and how much a workspace may keep (its plan's `storage_mb`, else config launchpad.files.quota_mb) */
final class Files
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public static function disk(): string
    {
        return (string) config('launchpad.files.disk', 'local');
    }

    /** @return list<string> the validation rules for one uploaded file; the type is read from the content, not taken from the name */
    public static function rules(): array
    {
        return ['required', 'file', 'max:'.(int) config('launchpad.files.max_kb', 10240), 'mimes:'.implode(',', (array) config('launchpad.files.mimes'))];
    }

    /** @return int|null bytes, or null for no limit */
    public function quota(Workspace $workspace): ?int
    {
        $mb = $this->entitlements->limitOr($workspace, 'storage_mb', (int) config('launchpad.files.quota_mb', 100));

        return $mb >= Entitlements::UNLIMITED ? null : $mb * 1024 * 1024;
    }

    public function used(Workspace $workspace): int
    {
        $used = (int) WorkspaceFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->sum('size');

        // the media library shares the space (its trash counts until it is emptied)
        if (config('launchpad.modules.media')) {
            $used += (int) MediaFile::withoutGlobalScope(WorkspaceScope::class)->withTrashed()->where('workspace_id', $workspace->id)->sum('size');
        }

        return $used;
    }

    /** @throws ValidationException when it would take the workspace over its quota */
    public function store(Workspace $workspace, UploadedFile $file, ?Model $by = null): WorkspaceFile
    {
        $quota = $this->quota($workspace);

        if ($quota !== null && $this->used($workspace) + $file->getSize() > $quota) {
            throw ValidationException::withMessages(['file' => __('This team has run out of file space. Delete a file or upgrade the plan.')]);
        }

        // read before the file is moved
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $name = Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $file->getClientOriginalName()) ?: 'file', 200, '');
        $size = (int) $file->getSize();
        $path = $file->storeAs("workspaces/{$workspace->id}", Str::random(40), ['disk' => self::disk()]);

        return WorkspaceFile::create([
            'workspace_id' => $workspace->id, 'uploaded_by' => $by?->getKey(), 'disk' => self::disk(),
            'path' => $path, 'name' => $name, 'mime' => $mime, 'size' => $size,
        ]);
    }

    /** Every file of a workspace that is going away, bytes included */
    public static function purge(Workspace $workspace): void
    {
        WorkspaceFile::withoutGlobalScope(WorkspaceScope::class)->where('workspace_id', $workspace->id)->get()->each->delete();
    }
}
