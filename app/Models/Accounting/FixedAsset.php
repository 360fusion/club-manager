<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasFactory;

    protected $table = 'accounting_fixed_assets';

    protected $fillable = [
        'club_id',
        'name',
        'category',
        'purchase_date',
        'purchase_cost',
        'bill_id',
        'depreciation_method',
        'useful_life_years',
        'salvage_value',
        'disposal_date',
        'disposal_proceeds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date:Y-m-d',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'disposal_date' => 'date:Y-m-d',
            'disposal_proceeds' => 'decimal:2',
        ];
    }

    public function isDisposed(): bool
    {
        return $this->disposal_date !== null;
    }

    /**
     * Accumulated depreciation posted to date (sum of every depreciation entry run).
     */
    public function getAccumulatedDepreciationAttribute(): float
    {
        return (float) $this->depreciationEntries()->sum('amount');
    }

    /**
     * Net book value = cost minus accumulated depreciation, never below salvage value.
     */
    public function getNetBookValueAttribute(): float
    {
        return max((float) $this->salvage_value, (float) $this->purchase_cost - $this->accumulated_depreciation);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return HasMany<FixedAssetDepreciationEntry, $this>
     */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class);
    }
}
