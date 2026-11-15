<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use App\Models\User;
use App\Services\StaticSite\StaticSiteBuilder;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Builds one queued version of a club's static website (StaticSiteBuilder), makes it the live one, then sends it to
 * every destination set to receive new versions automatically. Runs on the `sites` queue, apart from email and
 * newsletters. Ask for a version with request(), never by dispatching this directly: a club has at most one version
 * waiting, which is what keeps a burst of edits to one build.
 *
 * One build per club runs at a time (a second one waits its turn). The job only builds a version that is still
 * `queued`, so a copy that runs late (an earlier delayed job after Publish now, or a job a second worker picked up
 * again) does nothing. An automatic build that comes out the same as the live version is thrown away.
 */
class BuildStaticSite implements ShouldQueue
{
    use Queueable;

    /** How long after the last edit an automatic version is built, so a whole editing session goes out as one. */
    public const AFTER_EDITS_MINUTES = 30;

    public int $timeout = 900;

    /** A real error fails the job at once; waiting for the club's other build to finish doesn't count. */
    public int $maxExceptions = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SiteRelease $release)
    {
        $this->onQueue('sites');
    }

    /**
     * Asks for a new version. Automatic requests (after edits, nightly) join the version already waiting, if any.
     * Someone pressing Publish now turns a waiting automatic version into theirs and starts it straight away.
     */
    public static function request(Club $club, ?User $by = null, bool $automatic = false, ?CarbonInterface $at = null): SiteRelease
    {
        return Cache::lock("static-site-request-{$club->id}", 10)->block(5, function () use ($club, $by, $automatic, $at) {
            $waiting = SiteRelease::where('club_id', $club->id)->where('status', 'queued')->first();

            // A version that never started hours after it was due: the queue lost it (or no worker runs the sites queue).
            if ($waiting && $waiting->scheduled_for?->lt(now()->subHours(3))) {
                $waiting->update(['status' => 'failed', 'error' => 'This version waited hours and never started building. Check that a queue worker runs the "sites" queue.']);
                $waiting = null;
            }

            // Another edit while an automatic version waits: it moves on to 30 minutes after this edit (in steps of
            // at least 5 minutes, and at most 2 hours after the first), so it goes out once the editing is over.
            if ($waiting && $automatic && $waiting->automatic && $at) {
                $later = $at->min($waiting->created_at->copy()->addHours(2));

                if ($later->gt($waiting->scheduled_for->copy()->addMinutes(5))) {
                    $waiting->update(['scheduled_for' => $later]);
                    self::dispatch($waiting)->delay($later);
                }

                return $waiting;
            }

            if ($waiting && ($automatic || ! $waiting->automatic)) {
                return $waiting;
            }

            if ($waiting) {
                $waiting->update(['automatic' => false, 'created_by' => $by?->id, 'scheduled_for' => now()]);
                self::dispatch($waiting);

                return $waiting;
            }

            $release = SiteRelease::create([
                'club_id' => $club->id, 'created_by' => $by?->id, 'status' => 'queued',
                'automatic' => $automatic, 'scheduled_for' => $at ?? now(),
            ]);
            self::dispatch($release)->delay($at);

            return $release;
        });
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('static-site-build-'.$this->release->club_id))->releaseAfter(20)->expireAfter($this->timeout + 60)];
    }

    /** Keeps waiting while the club's earlier build runs; a delayed job's clock starts when it is dispatched. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function handle(StaticSiteBuilder $builder): void
    {
        $release = $this->release->fresh(['club', 'creator']);

        // Already built, or moved later by another edit (the job dispatched for the new time builds it).
        if ($release?->status !== 'queued' || ! $release->club || $release->scheduled_for?->gt(now()->addSeconds(30))) {
            return;
        }

        $club = $release->club;
        $automatic = $release->automatic;
        $release = $builder->build($club, $release->creator, skipIfUnchanged: $automatic, release: $release);

        if ($release?->status === 'failed' && $automatic) {
            StaticSiteBuilder::tellWebsiteEditors($club, 'Your website could not be updated',
                "A new version of {$club->name}'s website was being built after a change, and it failed:\n\n{$release->error}\n\nThe live version has not changed.");
        }

        if ($release?->status !== 'ready') {
            return;
        }

        SiteDeployTarget::where('club_id', $club->id)->get()
            ->filter(fn (SiteDeployTarget $target) => ! empty($target->config['auto_send']))
            ->each(fn (SiteDeployTarget $target) => DeployStaticSite::dispatch(SiteDeploy::create([
                'club_id' => $club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id,
                'user_id' => $release->created_by, 'status' => 'queued',
            ]), automatic: true));
    }

    /** The worker stopped the build (it ran out of time, or the server went down): don't leave it showing as running. */
    public function failed(?Throwable $exception): void
    {
        $release = $this->release->fresh();

        if ($release && in_array($release->status, ['queued', 'building'], true)) {
            $release->update(['status' => 'failed', 'error' => 'The build stopped before it finished. Try again; if it keeps happening, the website may be too big to build in 15 minutes.']);
            File::deleteDirectory($release->path());
        }
    }
}
