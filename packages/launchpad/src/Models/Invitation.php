<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Launchpad;
use Launchpad\Workspaces\Role;

class Invitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => Role::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime', 'permissions' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function customRole(): BelongsTo
    {
        return $this->belongsTo(CustomRole::class);
    }

    public function roleLabel(): string
    {
        return $this->customRole?->name ?? $this->role->label();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'invited_by');
    }

    /** Not accepted, not revoked, not expired */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }
}
