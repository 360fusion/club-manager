<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Launchpad\Launchpad;
use Launchpad\Workspaces\Concerns\BelongsToWorkspace;
use Launchpad\Workspaces\WorkspaceScope;

/** A file in a team's media library. Its bytes sit on the media disk under a random name; the name people see is only a label. */
class MediaFile extends Model
{
    use BelongsToWorkspace, Prunable, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        // deleting is the trash; only deleting for good takes the bytes
        static::forceDeleted(fn (self $file) => Storage::disk($file->disk)->delete($file->path));
    }

    /** image, video, audio, document or other, from what the file is (its mime), not what it is called */
    public static function kindOf(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf', str_starts_with($mime, 'text/'), str_starts_with($mime, 'application/vnd.openxmlformats-officedocument.') => 'document',
            default => 'other',
        };
    }

    public static function trashDays(): int
    {
        return (int) Setting::get('media_trash_days', config('launchpad.media.trash_days', 30));
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_file_tag');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'uploaded_by');
    }

    /** In the trash for longer than the trash days (the setting `media_trash_days`, else config launchpad.media.trash_days) */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope(WorkspaceScope::class)->onlyTrashed()->where('deleted_at', '<', now()->subDays(self::trashDays()));
    }
}
