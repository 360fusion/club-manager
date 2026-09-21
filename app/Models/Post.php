<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Casts\SanitizedHtmlBlocks;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
