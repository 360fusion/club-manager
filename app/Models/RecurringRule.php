<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'occurrence',
        'day_of_week',
        'active_months',
        'default_start_time',
        'default_rehearsal_time',
        'default_venue',
        'default_dress_code',
    ];

    protected $casts = [
        'active_months' => 'array',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
