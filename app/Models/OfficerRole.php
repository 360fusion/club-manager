<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficerRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'title',
        'short_code',
        'rank_level',
        'is_executive',
    ];

    protected $casts = [
        'is_executive' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OfficerAssignment::class);
    }
}
