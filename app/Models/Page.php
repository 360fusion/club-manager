<?php

namespace App\Models;

use App\Casts\SanitizedHtmlBlocks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * How much of the site's top menu a page shows.
     *
     * @var list<string>
     */
    public const HEADER_STYLES = ['full', 'logo_only', 'hidden'];

    protected $fillable = [
        'club_id',
        'title',
        'slug',
        'blocks',
        'meta_title',
        'meta_description',
        'share_image',
        'noindex',
        'is_published',
        'publish_at',
        'unpublish_at',
        'is_homepage',
        'is_members_only',
        'show_in_navigation',
        'show_in_footer',
        'header_style',
        'preview_token',
        'draft_blocks',
        'draft_content',
        'draft_saved_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => SanitizedHtmlBlocks::class,
            'noindex' => 'boolean',
            'is_published' => 'boolean',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'show_in_footer' => 'boolean',
            'draft_blocks' => SanitizedHtmlBlocks::class,
            'draft_content' => 'array',
            'draft_saved_at' => 'datetime',
            'is_homepage' => 'boolean',
            'is_members_only' => 'boolean',
            'show_in_navigation' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @var list<string>
     */
    protected $appends = ['lifecycle_status'];

    /**
     * The page's place in its life for the admin lists: off, scheduled, live or expired (see lifecycle()).
     */
    public function getLifecycleStatusAttribute(): string
    {
        return $this->lifecycle();
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    /**
     * Pages a visitor can see right now: switched on and inside their dates (if any). The homepage ignores its
     * dates, because a site must always have somewhere to land.
     *
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)->where(function (Builder $q) {
            $q->where('is_homepage', true)
                ->orWhere(fn (Builder $dates) => $dates
                    ->where(fn (Builder $from) => $from->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
                    ->where(fn (Builder $to) => $to->whereNull('unpublish_at')->orWhere('unpublish_at', '>', now())));
        });
    }

    public function isLive(): bool
    {
        return $this->lifecycle() === 'live';
    }

    /**
     * Where the page is in its life: off (not published), scheduled (waiting for its go-live date), live, or expired.
     */
    public function lifecycle(): string
    {
        if (! $this->is_published) {
            return 'off';
        }

        if ($this->is_homepage) {
            return 'live';
        }

        if ($this->publish_at && $this->publish_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->unpublish_at && $this->unpublish_at->lte(now())) {
            return 'expired';
        }

        return 'live';
    }

    public function hasDraft(): bool
    {
        return $this->draft_saved_at !== null;
    }
}
