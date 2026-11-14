<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One built copy of a club's public website as plain files, kept in its own folder on the `sites` disk (storage/app/sites). The
 * club's `current` link points at the live one, so publishing or going back to an older version is one switch.
 */
class SiteRelease extends Model
{
    public const KEEP = 5;

    protected $fillable = ['club_id', 'created_by', 'status', 'page_count', 'file_count', 'bytes', 'error', 'activated_at'];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    /** The folder every release of a club lives in, and its `current` link. */
    public static function clubRoot(int $clubId): string
    {
        return Storage::disk('sites')->path((string) $clubId);
    }

    public static function currentPath(int $clubId): string
    {
        return self::clubRoot($clubId).'/current';
    }

    public function path(): string
    {
        return self::clubRoot($this->club_id).'/releases/'.$this->id;
    }

    /** Whether the club's `current` link points at this release. */
    public function isCurrent(): bool
    {
        $target = @readlink(self::currentPath($this->club_id));

        return $target !== false && realpath($target) === realpath($this->path());
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
