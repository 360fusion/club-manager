<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\User;
use App\Services\StaticSite\StaticSiteBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds a new version of a club's static website (StaticSiteBuilder) and makes it the live one. One at a time per club:
 * a second request while one is queued is dropped, since the queued one will include the same content.
 */
class BuildStaticSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public Club $club, public ?User $user = null)
    {
        $this->onQueue('bulk');
    }

    public function uniqueId(): string
    {
        return (string) $this->club->id;
    }

    public function handle(StaticSiteBuilder $builder): void
    {
        $builder->build($this->club, $this->user);
    }
}
