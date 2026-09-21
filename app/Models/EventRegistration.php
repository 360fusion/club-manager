<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One booking for an event: the booker plus their guests (attendees).
 */
class EventRegistration extends Model
{
    /** Statuses that take up a place at the event. */
    public const HOLDING_PLACE = ['attending', 'tentative'];

    protected $fillable = [
        'event_id',
        'user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'token_hash',
        'notes',
        'subtotal',
        'discount_total',
        'booking_fee',
        'total',
        'payment_method_id',
        'payment_status',
        'amount_paid',
        'amount_refunded',
        'payment_reference',
        'due_at',
        'paid_at',
        'stripe_session_id',
        'stripe_payment_intent',
        'summons_sent_at',
        'reminder_sent_at',
    ];

    protected $hidden = ['token_hash', 'stripe_session_id', 'stripe_payment_intent'];

    /** The emailed link token, only set on the request that created a guest booking. */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'booking_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'summons_sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class, 'registration_id')->orderBy('is_guest')->orderBy('id');
    }

    /**
     * The booker's own line (the first attendee), if any.
     */
    public function booker(): ?EventAttendee
    {
        return $this->attendees->firstWhere('is_guest', false) ?? $this->attendees->first();
    }

    public function headcount(): int
    {
        return $this->attendees->count();
    }

    public function isGoing(): bool
    {
        return in_array($this->status, self::HOLDING_PLACE, true);
    }
}
