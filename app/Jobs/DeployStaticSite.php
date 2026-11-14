<?php

namespace App\Jobs;

use App\Models\SiteDeploy;
use App\Services\StaticSite\SiteDeployer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one release of a club's website to one of its destinations (SiteDeployer).
 */
class DeployStaticSite implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public SiteDeploy $deploy)
    {
        $this->onQueue('bulk');
    }

    public function handle(SiteDeployer $deployer): void
    {
        $deployer->run($this->deploy);
    }
}
