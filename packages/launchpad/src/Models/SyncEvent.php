<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;

class SyncEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
