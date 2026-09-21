<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'club_id',
        'code',
        'name',
        'type',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<JournalItem, $this>
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'account_id');
    }

    public function getBalanceAttribute(): float
    {
        $debits = (float) $this->journalItems()->sum('debit');
        $credits = (float) $this->journalItems()->sum('credit');

        // Assets and Expenses have normal Debit balances
        // Liabilities, Equity, and Revenue have normal Credit balances
        if (in_array($this->type, ['asset', 'expense'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
