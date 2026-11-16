<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Launchpad\Workspaces\Concerns\BelongsToWorkspace;
use Launchpad\Workspaces\WorkspaceScope;

/** A folder in a team's media library. Deleting one moves it to the trash; `model:prune` (daily) removes it for good after the trash days. */
class MediaFolder extends Model
{
    use BelongsToWorkspace, Prunable, SoftDeletes;

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'folder_id');
    }

    /** In the trash for longer than the trash days (the setting `media_trash_days`, else config launchpad.media.trash_days) */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope(WorkspaceScope::class)->onlyTrashed()->where('deleted_at', '<', now()->subDays(MediaFile::trashDays()));
    }
}
