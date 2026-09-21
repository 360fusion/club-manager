<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment method switched on for one event, with that event's own discount or fee if set.
 */
class EventPaymentMethod extends Model
{
    protected $fillable = [
        'event_id',
        'payment_method_id',
        'is_enabled',
        'adjustment_kind',
        'adjustment_mode',
        'adjustment_amount',
        'adjustment_scope',
        'due_days',
        'due_basis',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'adjustment_amount' => 'decimal:2',
            'due_days' => 'integer',
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
     * @return BelongsTo<ClubPaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ClubPaymentMethod::class, 'payment_method_id');
    }

    /**
     * The discount or fee that applies: this event's own, else the method's default.
     *
     * @return array{kind: string, mode: string, amount: float, scope: string}
     */
    public function adjustment(): array
    {
        $method = $this->method;
        $own = $this->adjustment_kind !== null;

        return [
            'kind' => $own ? $this->adjustment_kind : ($method->default_adjustment_kind ?? 'none'),
            'mode' => $own ? ($this->adjustment_mode ?? 'fixed') : ($method->default_adjustment_mode ?? 'fixed'),
            'amount' => (float) ($own ? $this->adjustment_amount : ($method->default_adjustment_amount ?? 0)),
            'scope' => $own ? ($this->adjustment_scope ?? 'per_person') : ($method->default_adjustment_scope ?? 'per_person'),
        ];
    }

    /**
     * @return array{days: ?int, basis: string}
     */
    public function due(): array
    {
        return [
            'days' => $this->due_days ?? $this->method?->due_days,
            'basis' => $this->due_basis ?? $this->method?->due_basis ?? 'before_event',
        ];
    }
}
