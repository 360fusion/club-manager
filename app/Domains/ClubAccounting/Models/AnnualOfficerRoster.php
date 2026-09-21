<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualOfficerRoster extends Model
{
    use HasFactory;

    protected $table = 'club_acc_annual_officer_rosters';

    protected $fillable = [
        'club_id',
        'meeting_id',
        'masonic_year',
        'status',
        'start_year',
        'end_year',
        'start_date',
        'end_date',
        'confirmed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return HasMany<AnnualOfficerAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AnnualOfficerAssignment::class, 'roster_id');
    }
}
