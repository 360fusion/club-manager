<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rsvp_deadline' => 'datetime',
            'booking_cutoff_days' => 'integer',
            'is_recurring' => 'boolean',
            'requires_payment' => 'boolean',
            'has_dining' => 'boolean',
            'price' => 'decimal:2',
            'dining_price' => 'decimal:2',
        ];
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(EventMenuItem::class);
    }

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(EventTicketTier::class);
    }

    public function promos(): HasMany
    {
        return $this->hasMany(EventPromo::class);
    }

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
