<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Launchpad;

/** A person a team's directory added through SCIM: the SCIM id of the user is this row's id */
class ScimIdentity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel());
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SsoConnection::class, 'sso_connection_id');
    }
}
