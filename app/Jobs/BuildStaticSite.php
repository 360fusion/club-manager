<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\User;
use App\Services\StaticSite\StaticSiteBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds a new version of a club's static website (StaticSiteBuilder) and makes it the live one, then sends it to every
 * destination set to receive new versions automatically. One at a time per club: a second request while one is queued
 * is dropped, since the queued one will include the same content. An automatic build (after an edit, or nightly) that
 * comes out the same as the live version is thrown away, so it neither pushes again nor pushes out an older version.
 */
class BuildStaticSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public Club $club, public ?User $user = null, public bool $automatic = false)
    {
        $this->onQueue('bulk');
    }

    public function uniqueId(): string
    {
        return (string) $this->club->id;
    }

    public function handle(StaticSiteBuilder $builder): void
    {
        $release = $builder->build($this->club, $this->user, skipIfUnchanged: $this->automatic);

        if ($release?->status === 'failed' && $this->automatic) {
            StaticSiteBuilder::tellWebsiteEditors($this->club, 'Your website could not be updated',
                "A new version of {$this->club->name}'s website was being built after a change, and it failed:\n\n{$release->error}\n\nThe live version has not changed.");
        }

        if ($release?->status !== 'ready') {
            return;
        }

        SiteDeployTarget::where('club_id', $this->club->id)->get()
            ->filter(fn (SiteDeployTarget $target) => ! empty($target->config['auto_send']))
            ->each(fn (SiteDeployTarget $target) => DeployStaticSite::dispatch(SiteDeploy::create([
                'club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id,
                'user_id' => $this->user?->id, 'status' => 'queued',
            ]), automatic: true));
    }
}
