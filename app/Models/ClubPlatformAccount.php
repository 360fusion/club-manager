<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lodge's Stripe account connected to the platform: an existing account it connected ("standard") or one
 * Stripe created for it ("express"). Payments go to that account and the platform's commission is taken.
 */
class ClubPlatformAccount extends Model
{
    public const STANDARD = 'standard';

    public const EXPRESS = 'express';

    protected $fillable = [
        'club_id',
        'type',
        'stripe_account_id',
        'country',
        'details_submitted',
        'charges_enabled',
        'payouts_enabled',
        'requirements',
        'commission_percent',
        'commission_fixed',
        'terms_accepted_at',
        'terms_accepted_by',
        'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            'details_submitted' => 'boolean',
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'requirements' => 'array',
            'commission_percent' => 'decimal:2',
            'commission_fixed' => 'decimal:2',
            'terms_accepted_at' => 'datetime',
            'disconnected_at' => 'datetime',
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
     * Whether card payments can be taken through this account right now.
     */
    public function canTakePayments(): bool
    {
        return $this->disconnected_at === null && $this->charges_enabled && $this->terms_accepted_at !== null;
    }
}
