<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'grand_lodge_id',
        'name',
        'code',
        'type',
        'region',
        'country',
        'website_url',
        'district_grand_master',
        'district_grand_secretary',
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
}
