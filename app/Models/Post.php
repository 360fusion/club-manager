<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Casts\SanitizedHtmlBlocks;
use App\Models\Concerns\HasVisibility;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use HasFactory, HasVisibility, InteractsWithMedia;

    protected $fillable = [
        'club_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'blocks',
        'attachments',
        'cover_image_url',
        'status',
        'visibility',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => SanitizedHtml::class,
            'blocks' => SanitizedHtmlBlocks::class,
            'attachments' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include published and active (non-expired) posts.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Narrow a query by the members-area news filters: `q` (title, summary, body), `tags`
     * (slugs, any of), `from` and `to` (dates, inclusive, on the published date).
     *
     * @param  array{q?: ?string, tags?: list<string>, from?: ?string, to?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $tags = array_values(array_filter($filters['tags'] ?? []));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $publishedOn = 'COALESCE(posts.published_at, posts.created_at)';

        return $query
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function (Builder $q) use ($like) {
                    $q->where('posts.title', 'like', $like)
                        ->orWhere('posts.excerpt', 'like', $like)
                        ->orWhere('posts.content', 'like', $like)
                        ->orWhere('posts.blocks', 'like', $like);
                });
            })
            ->when($tags !== [], fn (Builder $q) => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('news_tags.slug', $tags)))
            ->when($from, fn (Builder $q) => $q->whereRaw("{$publishedOn} >= ?", [Carbon::parse($from)->startOfDay()]))
            ->when($to, fn (Builder $q) => $q->whereRaw("{$publishedOn} <= ?", [Carbon::parse($to)->endOfDay()]));
    }

    /**
     * @return BelongsToMany<NewsTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NewsTag::class, 'news_tag_post');
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
