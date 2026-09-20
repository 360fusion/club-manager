<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultRank extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_type_id',
        'title',
        'abbreviation',
        'hierarchy_order',
    ];

    protected $casts = [
        'hierarchy_order' => 'integer',
    ];

    public function clubType(): BelongsTo
    {
        return $this->belongsTo(ClubType::class);
    }
}
