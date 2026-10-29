<?php

namespace App\Models;

use App\Services\DefaultSitePages;
use App\Support\Currencies;
use App\Support\MasonicRanks;
use App\Support\Months;
use App\Support\OrderColours;
use App\Support\ReservedClubSlugs;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Cashier\Billable as StripeBillable;
use Laravel\Paddle\Billable as PaddleBillable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Club extends Model implements HasMedia
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    /** @use InteractsWithMedia<Media> */
    use InteractsWithMedia;

    use PaddleBillable, StripeBillable {
        StripeBillable::subscription insteadof PaddleBillable;
        StripeBillable::subscriptions insteadof PaddleBillable;
        StripeBillable::onTrial insteadof PaddleBillable;
        StripeBillable::trialEndsAt insteadof PaddleBillable;
        StripeBillable::hasExpiredTrial insteadof PaddleBillable;
        StripeBillable::onGenericTrial insteadof PaddleBillable;
        StripeBillable::hasExpiredGenericTrial insteadof PaddleBillable;
        StripeBillable::subscribed insteadof PaddleBillable;
        StripeBillable::subscribedToProduct insteadof PaddleBillable;
        StripeBillable::subscribedToPrice insteadof PaddleBillable;
        StripeBillable::onProduct insteadof PaddleBillable;
        StripeBillable::onPrice insteadof PaddleBillable;
        StripeBillable::checkout insteadof PaddleBillable;
        StripeBillable::charge insteadof PaddleBillable;
        StripeBillable::newSubscription insteadof PaddleBillable;

        PaddleBillable::subscription as paddleSubscription;
        PaddleBillable::subscriptions as paddleSubscriptions;
        PaddleBillable::onTrial as paddleOnTrial;
        PaddleBillable::trialEndsAt as paddleTrialEndsAt;
        PaddleBillable::hasExpiredTrial as paddleHasExpiredTrial;
        PaddleBillable::onGenericTrial as paddleOnGenericTrial;
        PaddleBillable::hasExpiredGenericTrial as paddleHasExpiredGenericTrial;
        PaddleBillable::subscribed as paddleSubscribed;
        PaddleBillable::subscribedToProduct as paddleSubscribedToProduct;
        PaddleBillable::subscribedToPrice as paddleSubscribedToPrice;
        PaddleBillable::onProduct as paddleOnProduct;
        PaddleBillable::onPrice as paddleOnPrice;
        PaddleBillable::checkout as paddleCheckout;
        PaddleBillable::charge as paddleCharge;
        PaddleBillable::newSubscription as paddleNewSubscription;
    }

    protected $fillable = [
        'club_type_id',
        'province_id',
        'masonic_hall_id',
        'name',
        'slug',
        'email',
        'logo_url',
        'custom_domain',
        'domain_status',
        'domain_verified_at',
        'settings',
        'status',
        'default_provincial_header_text',
        'default_honorary_members_text',
        'lodge_number',
        'motto',
        'province_region',
        'town_city',
        'is_directory_listed',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * The colour of this club's order, for date tiles, calendar entries and chips.
     */
    public function colourKey(): string
    {
        return $this->clubType?->colour ?? OrderColours::DEFAULT;
    }

    protected static function booted(): void
    {
        static::saving(function (Club $club) {
            if (ReservedClubSlugs::isReserved((string) $club->slug)) {
                throw new InvalidArgumentException("The club slug \"{$club->slug}\" is reserved.");
            }
        });

        static::created(function (Club $club) {
            $club->ensureDefaultPages();
            NewsTag::ensureDefaults($club);
            MasonicRanks::seedClub($club);
        });

        // A summons stores the club name as it was when the meeting was created, so a lodge that
        // renames itself would keep printing the old name on every existing summons. Anything that
        // still matches the previous name was an automatic snapshot, not a deliberate override for
        // that one meeting, so it follows the rename; a hand-edited cover name is left alone.
        static::updated(function (Club $club) {
            $previousName = $club->getOriginal('name');

            if ($club->wasChanged('name') && $previousName) {
                Meeting::where('club_id', $club->id)
                    ->where('cover_club_name', $previousName)
                    ->update(['cover_club_name' => $club->name]);
            }
        });
    }

    /**
     * Ensure default navigation pages and standard website settings exist for this club.
     */
    public function ensureDefaultPages(): void
    {
        app(DefaultSitePages::class)->ensure($this);
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
     * The directory listing this club manages, when it was claimed from the lodge directory.
     *
     * @return HasOne<Lodge, $this>
     */
    public function lodge(): HasOne
    {
        return $this->hasOne(Lodge::class);
    }

    /**
     * Whether a meeting is this club's installation: its usual meeting in the installation month it
     * has set, or one that says so in its title.
     */
    public function isInstallationMeeting(Meeting $meeting): bool
    {
        if (stripos((string) $meeting->title, 'installation') !== false) {
            return true;
        }

        $month = Months::number($this->settings['installation_month'] ?? null);

        return $month !== null && (int) $meeting->meeting_date->format('n') === $month;
    }

    /**
     * The masonic hall this lodge or chapter meets in.
     *
     * @return BelongsTo<MasonicHall, $this>
     */
    public function masonicHall(): BelongsTo
    {
        return $this->belongsTo(MasonicHall::class);
    }

    /**
     * Where a new meeting is held unless the secretary says otherwise: the lodge's own hall ("Masonic Hall, High Street,
     * Stockton-on-Tees"), or nothing when the lodge has not said which hall it meets in.
     */
    public function defaultMeetingVenue(): string
    {
        $hall = $this->masonicHall;

        return $hall ? collect([$hall->name, $hall->address_line_1, $hall->town])->filter()->implode(', ') : '';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'rank', 'committee_role', 'member_number', 'status', 'phone', 'emergency_contact', 'dietary_notes', 'invitation_token', 'invited_at', 'invitation_accepted_at', 'invitation_reminded_at', 'invited_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<MembershipPlan, $this>
     */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Newsletter, $this>
     */
    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    /**
     * @return HasMany<NewsTag, $this>
     */
    public function newsTags(): HasMany
    {
        return $this->hasMany(NewsTag::class);
    }

    /**
     * @return HasMany<NewsletterType, $this>
     */
    public function newsletterTypes(): HasMany
    {
        return $this->hasMany(NewsletterType::class);
    }

    /**
     * @return HasMany<NewsletterSubscription, $this>
     */
    public function newsletterSubscriptions(): HasMany
    {
        return $this->hasMany(NewsletterSubscription::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    /**
     * @return HasOne<ClubPlatformAccount, $this>
     */
    public function platformAccount(): HasOne
    {
        return $this->hasOne(ClubPlatformAccount::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function mediaFolders(): HasMany
    {
        return $this->hasMany(MediaFolder::class);
    }

    /**
     * Check if a module feature is enabled for this club.
     */
    public function hasModule(string $moduleCode): bool
    {
        $typeModules = $this->clubType->available_modules ?? [];
        $clubSettings = $this->settings['enabled_modules'] ?? null;

        if (is_array($clubSettings)) {
            return in_array($moduleCode, $clubSettings, true);
        }

        return in_array($moduleCode, $typeModules, true);
    }

    /**
     * How many guests one member may bring to a meeting: the club's own setting, else a sensible ceiling.
     */
    public function maxGuestsPerMember(): int
    {
        $setting = $this->settings['max_guests_per_member'] ?? null;

        return is_numeric($setting) ? max(0, (int) $setting) : 10;
    }

    /**
     * How many bytes of media this club may store: its own setting, else the
     * platform default. Null means unlimited.
     */
    public function storageQuotaBytes(): ?int
    {
        $setting = $this->settings['storage_quota_mb'] ?? null;
        $quotaMb = is_numeric($setting) ? (int) $setting : config('club_media.default_storage_quota_mb');

        return is_numeric($quotaMb) ? max(0, (int) $quotaMb) * 1024 * 1024 : null;
    }

    /**
     * Bytes currently used by this club's media library, including trashed
     * files (still on disk until force-deleted) and all stored file versions.
     */
    public function storageUsedBytes(): int
    {
        $mediaBytes = (int) $this->media()->withTrashed()->sum('size');
        $versionBytes = (int) MediaVersion::where('club_id', $this->id)->sum('size');

        return $mediaBytes + $versionBytes;
    }

    /**
     * Whether a file of this many bytes still fits within the club's storage quota.
     */
    public function hasStorageFor(int $incomingBytes): bool
    {
        $quota = $this->storageQuotaBytes();

        return $quota === null || $this->storageUsedBytes() + $incomingBytes <= $quota;
    }

    /**
     * Storage usage and quota for this club, human-readable for display.
     *
     * @return array{used_bytes: int, quota_bytes: ?int, used_human: string, quota_human: ?string, percent: ?float}
     */
    public function storageSummary(): array
    {
        $used = $this->storageUsedBytes();
        $quota = $this->storageQuotaBytes();

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'used_human' => self::formatStorageBytes($used),
            'quota_human' => $quota !== null ? self::formatStorageBytes($quota) : null,
            'percent' => $quota !== null && $quota > 0 ? min(100, round($used / $quota * 100, 1)) : null,
        ];
    }

    public static function formatStorageBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    /**
     * How long an emailed account invitation stays valid.
     */
    public function inviteExpirationDays(): int
    {
        return max(1, (int) ($this->settings['invite_expiration_days'] ?? 14));
    }

    /**
     * Days after which an unanswered invitation gets one reminder; 0 switches reminders off.
     */
    public function inviteReminderDays(): int
    {
        return max(0, (int) ($this->settings['invite_reminder_days'] ?? 7));
    }

    /**
     * The currency this club keeps its books in: its own choice if valid, else
     * the currency of its province's or grand lodge's country, else the default.
     */
    public function currencyCode(): string
    {
        $chosen = strtoupper((string) ($this->settings['currency'] ?? ''));

        if (Currencies::isKnown($chosen)) {
            return $chosen;
        }

        $province = $this->province;
        $country = $province?->country ?: $province?->grandLodge?->country;

        return Currencies::forCountry($country) ?? Currencies::DEFAULT;
    }

    public function currencySymbol(): string
    {
        return Currencies::symbol($this->currencyCode());
    }

    /**
     * Whether the club has any financial records. Amounts carry no exchange
     * rate, so the currency may not change once there is money on the books.
     */
    public function currencyIsLocked(): bool
    {
        return DB::table('invoices')->where('club_id', $this->id)->exists()
            || DB::table('accounting_bills')->where('club_id', $this->id)->exists()
            || DB::table('accounting_journal_entries')->where('club_id', $this->id)->exists()
            || DB::table('club_acc_bank_transactions')->where('club_id', $this->id)->exists()
            || DB::table('club_acc_charity_collections')->where('club_id', $this->id)->exists()
            || DB::table('club_acc_charity_grants')->where('club_id', $this->id)->exists()
            || DB::table('club_acc_member_subscriptions')->where('club_id', $this->id)->exists();
    }

    /**
     * VAT is opt-in per club and off by default — most small lodges/clubs stay under
     * the UK VAT registration threshold and should never see VAT mechanics.
     *
     * @return array{enabled: bool, scheme: string, vat_number: ?string, flat_rate_percent: ?float, default_rate: float, registered_from: ?string}
     */
    public function vatSettings(): array
    {
        return array_merge([
            'enabled' => false,
            'scheme' => 'not_registered',
            'vat_number' => null,
            'flat_rate_percent' => null,
            'default_rate' => 20.00,
            'registered_from' => null,
        ], $this->settings['vat'] ?? []);
    }

    public function vatIsEnabled(): bool
    {
        return (bool) $this->vatSettings()['enabled'];
    }

    /**
     * Whether the club has posted any VAT-inclusive bills/invoices, once true a
     * normal admin may no longer change the VAT scheme or turn VAT off — the same
     * shape as currencyIsLocked().
     */
    public function vatSettingsAreLocked(): bool
    {
        return DB::table('invoices')->where('club_id', $this->id)->whereNotNull('vat_amount')->exists()
            || DB::table('accounting_bills')->where('club_id', $this->id)->whereNotNull('vat_amount')->exists();
    }

    /**
     * Whether a given financial year's books have been closed (see
     * AccountingPeriodClose) — new journal entries dated inside a closed year are
     * blocked for normal admins, mirroring vatSettingsAreLocked()'s shape.
     */
    /**
     * The month (1-12) the lodge's financial year ends in: April unless the treasurer chose another.
     */
    public function financialYearEndMonth(): int
    {
        $month = (int) ($this->settings['financial_year_end_month'] ?? 4);

        return $month >= 1 && $month <= 12 ? $month : 4;
    }

    /**
     * Financial years are numbered by the calendar year they end in: with an April year end,
     * 2026 runs from 1 May 2025 to 30 April 2026.
     */
    public function financialYearFor(CarbonInterface|string $date): int
    {
        $date = Carbon::parse($date);

        return $date->month > $this->financialYearEndMonth() ? $date->year + 1 : $date->year;
    }

    /**
     * The first and last day of financial year $year (see financialYearFor()).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function financialYearRange(int $year): array
    {
        $end = Carbon::create($year, $this->financialYearEndMonth(), 1)->endOfMonth();

        return [$end->copy()->addDay()->subYear()->startOfDay(), $end];
    }

    public function financialYearIsClosed(int $year): bool
    {
        return DB::table('accounting_period_closes')
            ->where('club_id', $this->id)
            ->where('financial_year', $year)
            ->exists();
    }

    /**
     * Off by default — a club that never sets this sees no change in behaviour.
     * When enabled, a bill/invoice at or above the amount is held for a second
     * admin's sign-off (see AccountingService::amountRequiresApproval()).
     *
     * @return array{enabled: bool, amount: ?float}
     */
    public function approvalThresholdSettings(): array
    {
        return array_merge([
            'enabled' => false,
            'amount' => null,
        ], $this->settings['approval_threshold'] ?? []);
    }

    /**
     * Whether the club's Charity/Benevolent Fund is separately registered with the Charity
     * Commission, which is what makes an Independent Examiner's Report a real filing obligation.
     *
     * @return array{registered: bool, charity_number: ?string}
     */
    public function charityCommissionSettings(): array
    {
        return array_merge([
            'registered' => false,
            'charity_number' => null,
        ], $this->settings['charity_commission'] ?? []);
    }
}
