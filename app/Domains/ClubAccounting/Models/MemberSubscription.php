<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_member_subscriptions';

    protected $fillable = [
        'club_id',
        'member_id',
        'tier_id',
        'billing_year',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
        'invoice_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => SubscriptionStatus::class,
        ];
    }

    public function balanceDue(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, (float) $this->amount_due - (float) $this->amount_paid)
        );
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTier::class, 'tier_id');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::Unpaid->value,
            SubscriptionStatus::PartiallyPaid->value,
            SubscriptionStatus::ArrearsWarning->value,
        ]);
    }

    public function scopeArrears(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::ArrearsWarning->value);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Paid->value);
    }

    public function scopeYear(Builder $query, int $year): Builder
    {
        return $query->where('billing_year', $year);
    }
}
