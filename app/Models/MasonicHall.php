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
        'latitude',
        'longitude',
    ];

    /** The places the directory's distance search looks a place name or postcode up in (Nominatim country codes). */
    public const SEARCH_COUNTRIES = 'gb,gg,je,im';

    protected static function booted(): void
    {
        // A new postcode means the stored position is wrong; `halls:geocode` looks it up again.
        static::saving(function (MasonicHall $hall) {
            if ($hall->exists && $hall->isDirty('postcode') && ! $hall->isDirty('latitude')) {
                $hall->latitude = null;
                $hall->longitude = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /**
     * The halls within this many miles of a point, nearest first, as hall id => miles.
     *
     * @return array<int, float>
     */
    public static function withinMiles(float $latitude, float $longitude, int $miles): array
    {
        // A box around the point narrows the query; the exact (great-circle) distance is worked out here.
        $latSpan = $miles / 69.0;
        $lngSpan = $miles / max(1.0, 69.0 * cos(deg2rad($latitude)));

        return self::query()
            ->whereBetween('latitude', [$latitude - $latSpan, $latitude + $latSpan])
            ->whereBetween('longitude', [$longitude - $lngSpan, $longitude + $lngSpan])
            ->get(['id', 'latitude', 'longitude'])
            ->mapWithKeys(fn (MasonicHall $hall) => [$hall->id => self::milesBetween($latitude, $longitude, $hall->latitude, $hall->longitude)])
            ->filter(fn (float $distance) => $distance <= $miles)
            ->sort()
            ->all();
    }

    public static function milesBetween(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $a = sin(deg2rad($toLat - $fromLat) / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin(deg2rad($toLng - $fromLng) / 2) ** 2;

        return 3958.8 * 2 * asin(min(1.0, sqrt($a)));
    }

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
