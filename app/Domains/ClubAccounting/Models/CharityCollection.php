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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collection_type' => CollectionType::class,
            'cash_amount' => 'decimal:2',
            'cheque_amount' => 'decimal:2',
        ];
    }

    public function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => (float)$this->cash_amount + (float)$this->cheque_amount
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
}
