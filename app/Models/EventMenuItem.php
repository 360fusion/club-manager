<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'category',
        'name',
        'description',
        'is_vegetarian',
        'is_vegan',
        'is_gf',
    ];

    protected function casts(): array
    {
        return [
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_gf' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
