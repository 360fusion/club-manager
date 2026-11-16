<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\AdminLog;
use Launchpad\Site\Markdown;

/** A notice staff post for people inside the app; Launchpad\Site\Announcements decides who sees it */
class Announcement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['plans' => 'array', 'dismissible' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(fn (self $a) => AdminLog::record('announcement.created', $a, "Announcement “{$a->title}” posted"));
        static::updated(fn (self $a) => AdminLog::saved($a, 'announcement.updated'));
        static::deleted(fn (self $a) => AdminLog::record('announcement.deleted', null, "Announcement “{$a->title}” removed"));
    }

    /** Started (or no start) and not yet ended */
    public function scopeLive(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** The body as HTML: staff-written Markdown, raw HTML stripped by the same renderer the site pages use */
    public function html(): string
    {
        return Markdown::html((string) $this->body);
    }
}
