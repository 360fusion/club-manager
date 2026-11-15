<?php

namespace App\Models;

use App\Support\ClubDomain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
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

    /**
     * While a club's live domain shows the published files, sites/hosts/{domain} links to its `current` version, so the
     * web server can answer those pages from disk by the Host header alone, without PHP (see the Caddyfile in
     * REMINDERS.md). Called whenever the club is saved; a domain that changed or stopped showing the files loses its link.
     */
    public static function linkHost(Club $club): void
    {
        $hosts = Storage::disk('sites')->path('hosts');
        $domains = array_unique(array_filter([$club->getPrevious()['custom_domain'] ?? null, $club->custom_domain]));

        foreach ($domains as $domain) {
            if (is_link($hosts.'/'.$domain)) {
                unlink($hosts.'/'.$domain);
            }
        }

        $domain = strtolower((string) $club->custom_domain);

        if (! empty($club->settings['static_site_serve']) && ClubDomain::isLive($club) && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
            File::ensureDirectoryExists($hosts);
            symlink('../'.$club->id.'/current', $hosts.'/'.$domain);
        }
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
