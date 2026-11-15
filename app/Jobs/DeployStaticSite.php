<?php

namespace App\Jobs;

use App\Models\SiteDeploy;
use App\Services\StaticSite\SiteDeployer;
use App\Services\StaticSite\StaticSiteBuilder;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Sends one release of a club's website to one of its destinations (SiteDeployer), on the `sites` queue. One send per
 * destination runs at a time, and only a send that is still `queued` runs, so a copy picked up again does nothing.
 */
class DeployStaticSite implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $maxExceptions = 1;

    public function __construct(public SiteDeploy $deploy, public bool $automatic = false)
    {
        $this->onQueue('sites');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('static-site-deploy-'.$this->deploy->site_deploy_target_id))->releaseAfter(20)->expireAfter($this->timeout + 60)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function handle(SiteDeployer $deployer): void
    {
        if ($this->deploy->fresh()?->status !== 'queued') {
            return;
        }

        $deployer->run($this->deploy);
        $deploy = $this->deploy->fresh(['club', 'target']);

        if ($this->automatic && $deploy?->status === 'failed' && $deploy->club) {
            StaticSiteBuilder::tellWebsiteEditors($deploy->club, 'Your website could not be sent to '.($deploy->target->name ?? 'its destination'),
                "The new version of {$deploy->club->name}'s website could not be sent to ".($deploy->target->name ?? 'its destination').":\n\n{$deploy->message}\n\nThe files there have not been fully updated; once the problem is fixed, send the live version again from the Publish screen.");
        }
    }
}
