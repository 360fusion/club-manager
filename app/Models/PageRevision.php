<?php

namespace App\Models;

use App\Casts\SanitizedHtmlBlocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earlier save of a page's content (see App\Services\PagePublisher).
 */
class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'club_id', 'user_id', 'title', 'meta_title', 'meta_description', 'share_image', 'blocks', 'source'];

    protected function casts(): array
    {
        return ['blocks' => SanitizedHtmlBlocks::class];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
