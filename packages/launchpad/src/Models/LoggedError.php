<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/** An error the app reported, once per place it happens (Launchpad\Health\ErrorLog writes them), shown on HQ's Errors page */
class LoggedError extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** Dealt with and not seen for a month, or still open and not seen for six: `model:prune` is scheduled daily */
    public function prunable(): Builder
    {
        return static::where(fn ($q) => $q->whereNotNull('resolved_at')->where('last_seen_at', '<', now()->subMonth()))
            ->orWhere('last_seen_at', '<', now()->subMonths(6));
    }
}
