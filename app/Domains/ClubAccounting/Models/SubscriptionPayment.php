<?php

namespace App\Domains\ClubAccounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment towards a member's subscription (SubscriptionBillingService::recordPayment).
 */
class SubscriptionPayment extends Model
{
    protected $table = 'club_acc_subscription_payments';

    protected $fillable = ['club_id', 'subscription_id', 'amount', 'paid_on', 'reference', 'bank_transaction_id', 'journal_entry_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date'];
    }

    /** @return BelongsTo<MemberSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }
}
