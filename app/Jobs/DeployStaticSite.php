<?php

namespace App\Jobs;

use App\Models\SiteDeploy;
use App\Services\StaticSite\SiteDeployer;
use App\Services\StaticSite\StaticSiteBuilder;
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

    public function __construct(public SiteDeploy $deploy, public bool $automatic = false)
    {
        $this->onQueue('bulk');
    }

    public function handle(SiteDeployer $deployer): void
    {
        $deployer->run($this->deploy);
        $deploy = $this->deploy->fresh(['club', 'target']);

        if ($this->automatic && $deploy?->status === 'failed' && $deploy->club) {
            StaticSiteBuilder::tellWebsiteEditors($deploy->club, 'Your website could not be sent to '.($deploy->target->name ?? 'its destination'),
                "The new version of {$deploy->club->name}'s website could not be sent to ".($deploy->target->name ?? 'its destination').":\n\n{$deploy->message}\n\nThe files there have not been fully updated; once the problem is fixed, send the live version again from the Publish screen.");
        }
    }
}
