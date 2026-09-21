<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasFactory, HasVisibility;

    protected $attributes = [
        'rsvp_audience' => 'club',
    ];

    protected $fillable = [
        'club_id',
        'title',
        'slug',
        'description',
        'location',
        'address_line_1',
        'address_line_2',
        'city',
        'county',
        'postcode',
        'starts_at',
        'ends_at',
        'is_recurring',
        'recurrence_rule',
        'requires_payment',
        'price',
        'has_dining',
        'dining_price',
        'rsvp_deadline',
        'booking_cutoff_days',
        'status',
        'visibility',
        'rsvp_audience',
        'capacity',
        'waitlist_enabled',
        'registration_opens_at',
        'allow_public_registration',
        'max_guests_per_booking',
        'booking_fee_type',
        'booking_fee_amount',
        'booking_fee_scope',
        'booking_fee_label',
        'price_display',
        'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rsvp_deadline' => 'datetime',
            'booking_cutoff_days' => 'integer',
            'rsvp_audience' => Visibility::class,
            'is_recurring' => 'boolean',
            'requires_payment' => 'boolean',
            'has_dining' => 'boolean',
            'price' => 'decimal:2',
            'dining_price' => 'decimal:2',
            'capacity' => 'integer',
            'waitlist_enabled' => 'boolean',
            'registration_opens_at' => 'datetime',
            'allow_public_registration' => 'boolean',
            'max_guests_per_booking' => 'integer',
            'booking_fee_amount' => 'decimal:2',
        ];
    }

    /**
     * Whether the viewer may respond to this event. RSVPs are open to the
     * audience chosen for the invite, and only to people who can see the event.
     */
    public function canBeRsvpedBy(?User $viewer): bool
    {
        return $this->isVisibleTo($viewer)
            && $this->rsvp_audience->includes($viewer, (int) $this->club_id);
    }

    /**
     * Whether a member can answer "going" in one tap. Events that take payment,
     * dining choices or a ticket tier need the full form first.
     */
    public function allowsQuickReply(): bool
    {
        return ! $this->requires_payment
            && ! $this->has_dining
            && ! $this->ticketTiers()->exists();
    }

    public function getFormattedLocationAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->county,
            $this->postcode,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : ($this->location ?? '');
    }

    public function getBookingCutoffAtAttribute(): ?Carbon
    {
        if ($this->rsvp_deadline) {
            return Carbon::parse($this->rsvp_deadline);
        }
        if ($this->booking_cutoff_days !== null && $this->starts_at) {
            return Carbon::parse($this->starts_at)->subDays((int) $this->booking_cutoff_days);
        }

        return null;
    }

    public function getIsBookingClosedAttribute(): bool
    {
        $cutoff = $this->booking_cutoff_at;

        return $cutoff ? Carbon::now()->isAfter($cutoff) : false;
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<EventMenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(EventMenuItem::class);
    }

    /**
     * @return HasMany<EventTicketTier, $this>
     */
    public function ticketTiers(): HasMany
    {
        return $this->hasMany(EventTicketTier::class);
    }

    /**
     * @return HasMany<EventPromo, $this>
     */
    public function promos(): HasMany
    {
        return $this->hasMany(EventPromo::class);
    }

    /**
     * @return HasMany<EventPaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(EventPaymentMethod::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * People (not bookings) holding a place: everyone on an attending or tentative booking.
     */
    public function placesTaken(): int
    {
        return (int) EventAttendee::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $this->id)->whereIn('status', EventRegistration::HOLDING_PLACE))
            ->count();
    }

    public function placesLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->placesTaken());
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->placesTaken() >= $this->capacity;
    }

    /**
     * Events people can see and book: drafts are only for the organisers.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePublished($query)
    {
        return $query->where('status', '!=', 'draft');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'attendance_status',
                'summons_sent_at',
                'reminder_sent_at',
                'attending_dining',
                'menu_selections',
                'dietary_requirements',
                'payment_status',
                'amount_paid',
                'checked_in_at',
            ])
            ->withTimestamps();
    }
}
