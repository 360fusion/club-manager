<?php

namespace App\Models\Accounting;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'club_id',
        'reference_number',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->items()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->items()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.001;
    }
}
