<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Models\Club;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CharityCollection extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_charity_collections';

    protected $fillable = [
        'club_id',
        'meeting_id',
        'collection_type',
        'cash_amount',
        'cheque_amount',
        'counted_by_member_id',
        'witnessed_by_member_id',
        'donor_member_id',
        'donor_name',
        'is_gift_aid_eligible',
        'gift_aid_status',
        'gift_aid_amount',
        'gift_aid_reconciled_at',
        'bank_transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collection_type' => CollectionType::class,
            'cash_amount' => 'decimal:2',
            'cheque_amount' => 'decimal:2',
            'is_gift_aid_eligible' => 'boolean',
            'gift_aid_amount' => 'decimal:2',
            'gift_aid_reconciled_at' => 'datetime',
        ];
    }

    public function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->cash_amount + (float) $this->cheque_amount
        );
    }

    public function calculatedGiftAid(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_gift_aid_eligible) {
                    return 0.00;
                }
                $total = (float) $this->cash_amount + (float) $this->cheque_amount;

                return round($total * 0.25, 2);
            }
        );
    }

    public function donorDisplayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->donor) {
                    return $this->donor->full_name;
                }
                if (! empty($this->donor_name)) {
                    return $this->donor_name;
                }
                if ($this->countedBy) {
                    return $this->countedBy->full_name.' (Steward / Recorder)';
                }

                return 'Anonymous Donor / Meeting Collection';
            }
        );
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'counted_by_member_id');
    }

    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'witnessed_by_member_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'donor_member_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
