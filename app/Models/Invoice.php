<?php

namespace App\Models;

use App\Domains\ClubAccounting\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'club_id',
        'user_id',
        'title',
        'amount',
        'net_amount',
        'vat_rate',
        'vat_amount',
        'status',
        'paid_at',
        'paid_by_user_id',
        'reconciled_at',
        'reconciled_bank_transaction_id',
        'media_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * Whether this invoice carries VAT (the club had VAT enabled when it was created).
     */
    public function hasVat(): bool
    {
        return $this->vat_amount !== null;
    }

    /**
     * Whether this invoice has been confirmed against an actual bank statement
     * line, independently of whether it has been marked paid.
     */
    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /**
     * @return BelongsTo<BankTransaction, $this>
     */
    public function reconciledBankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'reconciled_bank_transaction_id');
    }
}
