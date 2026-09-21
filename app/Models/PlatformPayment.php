<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One card payment taken through a lodge's connected Stripe account, and the commission the platform kept.
 */
class PlatformPayment extends Model
{
    protected $fillable = ['club_id', 'registration_id', 'payment_intent', 'currency', 'gross', 'commission', 'refunded'];

    protected function casts(): array
    {
        return ['gross' => 'decimal:2', 'commission' => 'decimal:2', 'refunded' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
