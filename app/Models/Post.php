<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasFactory;

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
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'attachments' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include published and active (non-expired) posts.
     */
    public function scopePublished($query)
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
