<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualOfficerAssignment extends Model
{
    use HasFactory;

    protected $table = 'club_acc_annual_officer_assignments';

    protected $fillable = [
        'roster_id',
        'member_id',
        'office',
        'category',
    ];

    /**
     * @return BelongsTo<AnnualOfficerRoster, $this>
     */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(AnnualOfficerRoster::class, 'roster_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function getLodgeOfficeAttribute(): ?LodgeOffice
    {
        return LodgeOffice::tryFrom($this->office);
    }
}
