<?php

namespace App\Domains\ClubAccounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberFestivalGiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_member_festival_giving';

    protected $fillable = [
        'member_id',
        'regular_giving_amount',
        'total_donated_to_date',
        'qualifies_for_jewel',
        'qualifies_for_bar',
    ];

    protected function casts(): array
    {
        return [
            'regular_giving_amount' => 'decimal:2',
            'total_donated_to_date' => 'decimal:2',
            'qualifies_for_jewel' => 'boolean',
            'qualifies_for_bar' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
