<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a booking, with their own ticket and meal choices.
 */
class EventAttendee extends Model
{
    protected $fillable = [
        'registration_id',
        'user_id',
        'name',
        'is_guest',
        'ticket_tier_id',
        'attending_dining',
        'starter_item_id',
        'main_item_id',
        'dessert_item_id',
        'dietary_requirements',
        'legacy_menu',
        'price',
        'checked_in_at',
        'table_label',
    ];

    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'attending_dining' => 'boolean',
            'legacy_menu' => 'array',
            'price' => 'decimal:2',
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    /**
     * @return BelongsTo<EventTicketTier, $this>
     */
    public function ticketTier(): BelongsTo
    {
        return $this->belongsTo(EventTicketTier::class, 'ticket_tier_id');
    }

    /**
     * @return BelongsTo<EventMenuItem, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(EventMenuItem::class, 'starter_item_id');
    }

    /**
     * @return BelongsTo<EventMenuItem, $this>
     */
    public function main(): BelongsTo
    {
        return $this->belongsTo(EventMenuItem::class, 'main_item_id');
    }

    /**
     * @return BelongsTo<EventMenuItem, $this>
     */
    public function dessert(): BelongsTo
    {
        return $this->belongsTo(EventMenuItem::class, 'dessert_item_id');
    }

    /**
     * The meal as readable text: linked dishes, else any older free-text choices.
     *
     * @return array{starter: ?string, main: ?string, dessert: ?string}
     */
    public function mealSummary(): array
    {
        $legacy = $this->legacy_menu ?? [];

        return [
            'starter' => $this->starter?->name ?? ($legacy['starter'] ?? null),
            'main' => $this->main?->name ?? ($legacy['main'] ?? null),
            'dessert' => $this->dessert?->name ?? ($legacy['dessert'] ?? null),
        ];
    }
}
