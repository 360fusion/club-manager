<?php

namespace App\Models\Accounting;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriodClose extends Model
{
    use HasFactory;

    protected $table = 'accounting_period_closes';

    protected $fillable = [
        'club_id',
        'financial_year',
        'closed_at',
        'closed_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'financial_year' => 'integer',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
