<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FestivalTarget extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_festival_targets';

    protected $fillable = [
        'club_id',
        'festival_name',
        'relief_chest_ref',
        'target_amount',
        'bronze_tier',
        'silver_tier',
        'gold_tier',
        'platinum_tier',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'bronze_tier' => 'decimal:2',
            'silver_tier' => 'decimal:2',
            'gold_tier' => 'decimal:2',
            'platinum_tier' => 'decimal:2',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function getCurrentTier(float $totalRaised): string
    {
        if ($totalRaised >= (float) $this->platinum_tier) {
            return 'Platinum Honor';
        }
        if ($totalRaised >= (float) $this->gold_tier) {
            return 'Gold Honor';
        }
        if ($totalRaised >= (float) $this->silver_tier) {
            return 'Silver Honor';
        }
        if ($totalRaised >= (float) $this->bronze_tier) {
            return 'Bronze Honor';
        }

        return 'Participating Lodge';
    }

    public function getPercentage(float $totalRaised): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0.0;
        }

        return min(100.0, round(($totalRaised / (float) $this->target_amount) * 100, 1));
    }
}
