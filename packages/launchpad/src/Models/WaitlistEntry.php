<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;

/** Somebody waiting to be let in while sign-up is closed (Launchpad\Accounts\Waitlist) */
class WaitlistEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['invited_at' => 'datetime'];
    }
}
