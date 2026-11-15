<?php

namespace App\Observers;

use App\Jobs\BuildStaticSite;
use App\Models\Club;
use App\Models\SiteRelease;
use App\Services\StaticSite\StaticSiteBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Website content changed (a page, article, event, redirect or the club's own settings): when the club's domain shows
 * the published files or a destination takes new versions automatically, a new version is built a couple of minutes later. BuildStaticSite is unique per club, so a burst
 * of edits makes one build, and it reads the content when it runs.
 */
class RebuildsStaticSite
{
    public function saved(Model $model): void
    {
        if ($model instanceof Club && $model->wasChanged(['settings', 'custom_domain', 'domain_status'])) {
            SiteRelease::linkHost($model);
        }

        $this->queue($model);
    }

    public function deleted(Model $model): void
    {
        $this->queue($model);
    }

    private function queue(Model $model): void
    {
        $club = $model instanceof Club ? $model : Club::find($model->getAttribute('club_id'));

        if ($club && StaticSiteBuilder::keepsUpdated($club)) {
            BuildStaticSite::dispatch($club, automatic: true)->delay(now()->addMinutes(2));
        }
    }
}
