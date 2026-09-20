<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultOfficerRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_type_id',
        'title',
        'short_code',
        'rank_level',
        'is_executive',
        'category',
    ];

    protected $casts = [
        'is_executive' => 'boolean',
        'rank_level' => 'integer',
    ];

    public function clubType(): BelongsTo
    {
        return $this->belongsTo(ClubType::class);
    }
}
