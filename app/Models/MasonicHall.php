<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasonicHall extends Model
{
    use HasFactory;

    /**
     * What sort of place a lodge meets in. Lodges also meet in hotels, clubs and schools, so those
     * are kept in the same list but told apart from masonic halls.
     */
    public const KINDS = [
        'hall' => 'Masonic hall',
        'hotel' => 'Hotel, inn or pub',
        'club' => 'Club',
        'school' => 'School or college',
        'other' => 'Other venue',
    ];

    protected $fillable = [
        'province_id',
        'name',
        'kind',
        'slug',
        'address_line_1',
        'address_line_2',
        'town',
        'county',
        'postcode',
        'country',
        'telephone',
        'email',
        'website_url',
        'source_url',
        'ugle_url',
    ];

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The lodges, chapters and other bodies that meet here.
     *
     * @return HasMany<Club, $this>
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * The address on one line, skipping any part that is empty.
     */
    public function fullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->town,
            $this->county,
            $this->postcode,
        ]));
    }
}
