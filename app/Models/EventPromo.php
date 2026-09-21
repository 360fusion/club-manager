<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPromo extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'code',
        'discount_type',
        'discount_amount',
        'max_uses',
        'uses_count',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
