<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $table = 'accounting_budgets';

    protected $fillable = [
        'club_id',
        'financial_year',
        'account_id',
        'budgeted_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'financial_year' => 'integer',
            'budgeted_amount' => 'decimal:2',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
