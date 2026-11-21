<?php

namespace App\Models;

use Database\Factories\PageTimingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One page load or in-app visit as a member's device measured it (see the migration). Kept 90 days.
 */
class PageTiming extends Model
{
    /** @use HasFactory<PageTimingFactory> */
    use HasFactory;

    use Prunable;

    public const LOAD = 'load';

    public const VISIT = 'visit';

    public const UPDATED_AT = null;

    protected $fillable = ['page', 'kind', 'ms', 'server_ms', 'cached', 'device', 'installed'];

    protected function casts(): array
    {
        return [
            'cached' => 'boolean',
            'installed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
