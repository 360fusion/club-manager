<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in a booking's payment history. Rows are only ever added: correcting a mistake means
 * adding a new line, so the history always shows who did what and when.
 */
class EventPaymentLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'event_payment_log';

    protected $fillable = [
        'registration_id',
        'action',
        'amount',
        'method',
        'comment',
        'user_id',
        'source',
        'received_at',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment history cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Payment history cannot be deleted.'));
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
