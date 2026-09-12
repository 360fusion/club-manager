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
        'available_modules',
        'default_settings',
    ];

    protected function casts(): array
    {
        return [
            'available_modules' => 'array',
            'default_settings' => 'array',
        ];
    }

    public function clubs(): HasMany
    {
        return $table = $this->hasMany(Club::class);
    }
}
