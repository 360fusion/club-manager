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
        'method_adjustment',
        'promo_code',
        'promo_discount',
        'due_at',
        'paid_at',
        'stripe_session_id',
        'stripe_payment_intent',
        'paypal_order_id',
        'paypal_capture_id',
        'summons_sent_at',
        'reminder_sent_at',
    ];

    protected $hidden = ['token_hash', 'stripe_session_id', 'stripe_payment_intent', 'paypal_order_id', 'paypal_capture_id'];

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
            'method_adjustment' => 'decimal:2',
            'promo_discount' => 'decimal:2',
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

    /**
     * @return BelongsTo<ClubPaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(ClubPaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return HasMany<EventPaymentLog, $this>
     */
    public function paymentLog(): HasMany
    {
        return $this->hasMany(EventPaymentLog::class, 'registration_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * What is still owed.
     */
    public function balanceDue(): float
    {
        return max(0.0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    /**
     * The status the money says: paid in full, part paid, or unpaid. Waived and refunded are decisions
     * a person made, so they stay until someone changes them.
     */
    public function statusFromAmounts(): string
    {
        if (in_array($this->payment_status, ['waived', 'refunded'], true)) {
            return $this->payment_status;
        }

        if ((float) $this->total <= 0) {
            return 'paid';
        }

        $paid = (float) $this->amount_paid;

        return $paid + 0.001 >= (float) $this->total ? 'paid' : ($paid > 0 ? 'part_paid' : 'unpaid');
    }

    /**
     * The reference someone quotes when paying by bank, unique to this booking.
     */
    public function makeReference(?ClubPaymentMethod $method = null): string
    {
        $prefix = strtoupper($method?->bankDetails()['reference_prefix'] ?? '') ?: 'EVT';

        return sprintf('%s-%d-%05d', $prefix, $this->event_id, $this->id);
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
