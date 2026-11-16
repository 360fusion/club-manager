<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** One API token's calls on one day (written after the response by AuthenticateApiToken::terminate) */
class ApiTokenUsage extends Model
{
    use MassPrunable;

    protected $table = 'api_token_usage';

    public $timestamps = false;

    protected $guarded = [];

    /** Days older than config launchpad.api.usage_days (90): `model:prune` is scheduled daily */
    public function prunable(): Builder
    {
        return static::where('day', '<', now()->subDays((int) config('launchpad.api.usage_days', 90))->toDateString());
    }

    protected function casts(): array
    {
        return ['day' => 'date'];
    }
}
