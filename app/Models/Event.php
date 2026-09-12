<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'title',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'is_recurring',
        'recurrence_rule',
        'requires_payment',
        'price',
        'has_dining',
        'dining_price',
        'rsvp_deadline',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rsvp_deadline' => 'datetime',
            'is_recurring' => 'boolean',
            'requires_payment' => 'boolean',
            'has_dining' => 'boolean',
            'price' => 'decimal:2',
            'dining_price' => 'decimal:2',
        ];
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
