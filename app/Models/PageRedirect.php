<?php

namespace App\Models;

use App\Observers\RebuildsStaticSite;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An old page slug, kept working as a redirect to where that page lives now.
 */
#[ObservedBy(RebuildsStaticSite::class)]
class PageRedirect extends Model
{
    protected $fillable = ['club_id', 'page_id', 'old_slug'];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
