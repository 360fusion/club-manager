<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Launchpad\Admin\AdminLog;
use Launchpad\Launchpad;
use Launchpad\Site\Markdown;

/** A post on the website's blog, written by staff in HQ */
class BlogPost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(fn (self $p) => AdminLog::record('blog_post.created', $p, "Blog post “{$p->title}” written"));
        static::updated(fn (self $p) => AdminLog::saved($p, 'blog_post.updated'));
        static::deleted(fn (self $p) => AdminLog::record('blog_post.deleted', null, "Blog post “{$p->title}” removed"));
        // the sitemap is kept for an hour; a post changing is a reason to make it again
        static::saved(fn () => Cache::forget('launchpad.sitemap'));
        static::deleted(fn () => Cache::forget('launchpad.sitemap'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'author_id');
    }

    /** Published, and its time has come */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function html(): string
    {
        return Markdown::html((string) $this->body);
    }
}
