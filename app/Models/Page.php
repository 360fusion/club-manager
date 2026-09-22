<?php

namespace App\Models;

use App\Casts\SanitizedHtmlBlocks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'club_id',
        'title',
        'slug',
        'blocks',
        'meta_title',
        'meta_description',
        'is_published',
        'is_homepage',
        'is_members_only',
        'show_in_navigation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => SanitizedHtmlBlocks::class,
            'is_published' => 'boolean',
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
}
