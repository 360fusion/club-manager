<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Launchpad\Workspaces\Concerns\BelongsToWorkspace;

/** A label a team puts on files in its media library */
class MediaTag extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'media_file_tag');
    }
}
