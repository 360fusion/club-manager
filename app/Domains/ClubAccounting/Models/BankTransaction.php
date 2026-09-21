<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_bank_transactions';

    protected $fillable = [
        'club_id',
        'bank_account_id',
        'bank_import_id',
        'transaction_date',
        'raw_description',
        'reference',
        'amount',
        'balance_after',
        'transaction_hash',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'status' => BankTransactionStatus::class,
        ];
    }

    public function isIncome(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->amount > 0
        );
    }

    public function isExpense(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->amount < 0
        );
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<BankImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(BankImport::class, 'bank_import_id');
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('status', BankTransactionStatus::Unmatched->value);
    }

    public function scopeMatched(Builder $query): Builder
    {
        return $query->where('status', BankTransactionStatus::Matched->value);
    }

    public function scopeIgnored(Builder $query): Builder
    {
        return $query->where('status', BankTransactionStatus::Ignored->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty(trim($term))) {
            return $query;
        }

        $search = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($search) {
            $q->where('raw_description', 'like', $search)
                ->orWhere('reference', 'like', $search)
                ->orWhere('transaction_hash', 'like', $search);
        });
    }
}
