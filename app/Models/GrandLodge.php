<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GrandLodge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'short_name',
        'country',
        'website_url',
        'description',
        'grand_ranks',
        'provincial_ranks',
    ];

    protected function casts(): array
    {
        return [
            'grand_ranks' => 'array',
            'provincial_ranks' => 'array',
        ];
    }

    /**
     * Get the provinces under this Grand Lodge.
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /**
     * Get all clubs/lodges associated with this Grand Lodge through its provinces.
     */
    public function clubs(): HasManyThrough
    {
        return $this->hasManyThrough(Club::class, Province::class);
    }
}
