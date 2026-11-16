<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean'];
    }

    public function wants(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }
}
