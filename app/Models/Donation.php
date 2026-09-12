<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'campaign_name',
        'target_amount',
        'current_amount',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(DonationContribution::class);
    }

    public function getPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) return 100;
        return min(100, round(($this->current_amount / $this->target_amount) * 100, 1));
    }
}
