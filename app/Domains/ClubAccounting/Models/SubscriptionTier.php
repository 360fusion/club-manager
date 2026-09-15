<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_subscription_tiers';

    protected $fillable = [
        'club_id',
        'name',
        'annual_amount',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'subscription_tier_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'tier_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
