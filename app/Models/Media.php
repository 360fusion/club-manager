<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    use SoftDeletes;

    /**
     * How many earlier versions of a replaced or cropped file are kept. Each one is a full copy of the file.
     */
    public const KEEP_VERSIONS = 5;

    /**
     * How long a file stays in the Trash bin before it is deleted for good.
     */
    public const TRASH_DAYS = 30;

    protected static function booted(): void
    {
        // Removing a file for good also removes the copies kept in its version history, which live on their own disk.
        static::forceDeleting(fn (Media $media) => $media->deleteVersionFiles());
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MediaVersion::class)->latest();
    }

    /**
     * Delete the oldest versions (rows and files) beyond the newest `$keep`. Returns how many were removed.
     */
    public function pruneVersions(int $keep = self::KEEP_VERSIONS): int
    {
        $old = $this->versions()->reorder()->orderByDesc('created_at')->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->get();

        foreach ($old as $version) {
            Storage::disk($version->disk)->delete($version->path);
            $version->delete();
        }

        return $old->count();
    }

    /**
     * Delete the stored copies of every version (the rows go with the file itself).
     */
    public function deleteVersionFiles(): void
    {
        foreach ($this->versions()->get() as $version) {
            Storage::disk($version->disk)->delete($version->path);
        }
    }
}
