<?php

namespace App\Models;

use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A lodge, chapter or other body in the public directory. Most are unmanaged listings; a managed
 * one points at the club account that runs it through `club_id`.
 */
class Lodge extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'dormant', 'suspended', 'erased'];

    protected $fillable = [
        'club_type_id',
        'province_id',
        'masonic_hall_id',
        'name',
        'number',
        'slug',
        'status',
        'meets_text',
        'installation_month',
        'website_url',
        'description',
        'source_url',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClubType, $this>
     */
    public function clubType(): BelongsTo
    {
        return $this->belongsTo(ClubType::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<MasonicHall, $this>
     */
    public function masonicHall(): BelongsTo
    {
        return $this->belongsTo(MasonicHall::class);
    }

    /**
     * The club account that manages this lodge, when it has been claimed.
     *
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<LodgeSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(LodgeSchedule::class);
    }

    /**
     * @return HasMany<LodgeSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(LodgeSource::class);
    }

    /**
     * The people who follow this lodge. For sending them notices only: it is never shown to anyone,
     * because a follow is private.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lodge_follows')->withPivot(['in_calendar', 'notify_summons']);
    }

    /**
     * @return HasMany<LodgeClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(LodgeClaim::class);
    }

    /**
     * Whether the meeting on $date is the installation: the lodge's usual meeting in its
     * installation month.
     */
    public function isInstallationOn(CarbonInterface $date): bool
    {
        return $this->installation_month !== null && (int) $date->format('n') === (int) $this->installation_month;
    }

    public function installationMonthName(): ?string
    {
        return Months::name($this->installation_month);
    }

    public function isManaged(): bool
    {
        return $this->club_id !== null;
    }

    /**
     * The name with its order attached ("Cowpen" becomes "Cowpen Lodge"), because some sources
     * leave the word out.
     */
    public function displayName(): string
    {
        return self::displayNameFor($this->name, $this->clubType?->code);
    }

    public static function displayNameFor(string $name, ?string $typeCode): string
    {
        $word = match ($typeCode) {
            'craft_lodge', 'mark_lodge', 'royal_ark_mariner' => 'Lodge',
            'royal_arch', 'rose_croix' => 'Chapter',
            'knights_templar' => 'Preceptory',
            'cryptic_council' => 'Council',
            default => null,
        };

        if ($word === null || preg_match('/\b(lodge|chapter|preceptory|council|conclave|tabernacle|college|consistory)\b/i', $name)) {
            return $name;
        }

        return $name.' '.$word;
    }

    /**
     * The URL slug: the full name and the number, so a lodge and the chapter that shares its
     * number never collide.
     */
    public static function slugFor(string $name, ?string $number, ?string $typeCode): string
    {
        return Str::slug(trim(self::displayNameFor($name, $typeCode).' '.$number));
    }

    /**
     * @param  Builder<Lodge>  $query
     * @return Builder<Lodge>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
