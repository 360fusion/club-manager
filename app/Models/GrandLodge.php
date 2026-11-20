<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GrandLodge extends Model
{
    /** @use HasFactory<Factory<self>> */
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
        'per_capita_rate',
    ];

    protected function casts(): array
    {
        return [
            'per_capita_rate' => 'decimal:2',
            'grand_ranks' => 'array',
            'provincial_ranks' => 'array',
        ];
    }

    /**
     * Get the provinces under this Grand Lodge.
     *
     * @return HasMany<Province, $this>
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /**
     * Get all clubs/lodges associated with this Grand Lodge through its provinces.
     *
     * @return HasManyThrough<Club, Province, $this>
     */
    public function clubs(): HasManyThrough
    {
        return $this->hasManyThrough(Club::class, Province::class);
    }
}
