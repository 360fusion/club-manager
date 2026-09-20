<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'colour',
        'description',
        'website_url',
        'available_modules',
        'default_settings',
        'terminology',
        'rulers_schema',
    ];

    protected function casts(): array
    {
        return [
            'available_modules' => 'array',
            'default_settings' => 'array',
            'terminology' => 'array',
            'rulers_schema' => 'array',
        ];
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    public function defaultOfficerRoles(): HasMany
    {
        return $this->hasMany(DefaultOfficerRole::class);
    }

    public function defaultRanks(): HasMany
    {
        return $this->hasMany(DefaultRank::class);
    }
}
