<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'grand_lodge_id',
        'name',
        'code',
        'region',
        'country',
        'website_url',
        'provincial_grand_master',
        'provincial_grand_secretary',
        'address_line_1',
        'address_line_2',
        'town',
        'county',
        'postcode',
        'telephone',
        'email',
        'twitter_url',
        'facebook_url',
        'description',
    ];

    /**
     * Get the governing Grand Lodge.
     */
    public function grandLodge(): BelongsTo
    {
        return $this->belongsTo(GrandLodge::class);
    }

    /**
     * Get the clubs/lodges associated with this Masonic province.
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
