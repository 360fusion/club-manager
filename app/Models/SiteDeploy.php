<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One push of a release to a target, and what happened. Rows are only ever added and moved from queued to done/failed.
 */
class SiteDeploy extends Model
{
    protected $fillable = ['club_id', 'site_deploy_target_id', 'site_release_id', 'user_id', 'status', 'files_sent', 'files_deleted', 'message'];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<SiteDeployTarget, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(SiteDeployTarget::class, 'site_deploy_target_id');
    }

    /**
     * @return BelongsTo<SiteRelease, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(SiteRelease::class, 'site_release_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
