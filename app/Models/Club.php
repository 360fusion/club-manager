<?php

namespace App\Models;

use App\Support\Currencies;
use App\Support\OrderColours;
use App\Support\ReservedClubSlugs;
use App\Support\SiteThemes;
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
    use HasFactory, InteractsWithMedia;
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
        $settings = $this->settings ?? [];
        $settingsUpdated = false;

        if (empty($settings['website_theme'])) {
            $settings['website_theme'] = SiteThemes::DEFAULT;
            $settingsUpdated = true;
        }

        if (empty($settings['contact_email'])) {
            $settings['contact_email'] = $this->email;
            $settingsUpdated = true;
        }

        if (empty($settings['seo_title_suffix'])) {
            $settings['seo_title_suffix'] = ' - '.$this->name;
            $settingsUpdated = true;
        }

        if (empty($settings['footer_copyright'])) {
            $settings['footer_copyright'] = '© '.date('Y').' '.$this->name.'. All rights reserved.';
            $settingsUpdated = true;
        }

        if (! array_key_exists('header_layout', $settings)) {
            $settings['header_layout'] = 'logo_left';
            $settings['header_show_logo'] = true;
            $settings['header_show_tagline'] = true;
            $settings['header_cta_enabled'] = false;
            $settings['header_show_account_links'] = true;
            $settings['footer_layout'] = 'simple';
            $settings['footer_show_social'] = true;
            $settings['footer_show_nav'] = false;
            $settingsUpdated = true;
        }

        if ($settingsUpdated) {
            $this->settings = $settings;
            $this->save();
        }

        $defaultPages = [
            [
                'title' => 'Home',
                'slug' => 'home',
                'is_homepage' => true,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 1,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Welcome to '.$this->name,
                        'subtitle' => 'Official Website & Portal',
                        'cta_text' => 'Join Us',
                        'cta_link' => '/site/'.$this->slug.'/join-us',
                    ],
                    [
                        'type' => 'news_feed',
                        'heading' => 'Latest News & Updates',
                    ],
                    [
                        'type' => 'events_calendar',
                        'heading' => 'Upcoming Events & Meetings',
                    ],
                ],
            ],
            [
                'title' => 'About',
                'slug' => 'about',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 2,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'About '.$this->name,
                        'subtitle' => 'History, Values & Community',
                    ],
                    [
                        'type' => 'rich_text',
                        'heading' => 'Our History & Mission',
                        'content' => '<p>Welcome to '.e($this->name).'. Our organization is dedicated to fostering community, excellence, and fellowship.</p>',
                    ],
                ],
            ],
            [
                'title' => 'Join Us',
                'slug' => 'join-us',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 3,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Join '.$this->name,
                        'subtitle' => 'Become a Member Today',
                    ],
                    [
                        'type' => 'pricing_cards',
                        'heading' => 'Membership Options & Dues',
                    ],
                    [
                        'type' => 'rich_text',
                        'heading' => 'Application Process',
                        'content' => '<p>To enquire about membership, please reach out to our secretary or complete our visitor registration form.</p>',
                    ],
                ],
            ],
            [
                'title' => 'News',
                'slug' => 'news',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 4,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Club News & Updates',
                        'subtitle' => 'Latest Articles & Announcements',
                    ],
                    [
                        'type' => 'news_feed',
                        'heading' => 'Recent Articles',
                    ],
                ],
            ],
            [
                'title' => 'Contact',
                'slug' => 'contact',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 5,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Contact Us',
                        'subtitle' => 'Get in touch with our team',
                    ],
                    [
                        'type' => 'contact_details',
                        'eyebrow' => 'CONTACT',
                        'title' => 'Get in Touch',
                        'description' => 'Whether you\'re interested in joining '.$this->name.', a visiting member, or simply want to learn more, we\'d be delighted to hear from you.',
                    ],
                    [
                        'type' => 'contact_form',
                        'heading' => 'Send Us a Message',
                        'subtitle' => 'Have questions or need assistance? Fill out the form below.',
                        'recipient_email' => $this->settings['contact_email'] ?? $this->email,
                        'cc_emails' => '',
                        'name_required' => true,
                        'email_required' => true,
                        'phone_required' => false,
                        'message_required' => true,
                        'button_text' => 'Send Message',
                        'success_message' => 'Thank you! Your message has been sent successfully.',
                    ],
                ],
            ],
        ];

        // Only ever seeded once: after that, a missing slug means an admin renamed or removed that page on
        // purpose, not that it needs putting back. Without this flag, renaming "About" away would leave a
        // fresh blank "About" reappearing every time the site is visited.
        if (! empty($settings['default_pages_seeded'])) {
            $home = $this->pages()->where('slug', 'home')->first();

            if ($home && $home->title !== 'Home') {
                $home->update(['title' => 'Home']);
            }

            return;
        }

        foreach ($defaultPages as $def) {
            if (! $this->pages()->where('slug', $def['slug'])->exists()) {
                $this->pages()->create($def);
            }
        }

        $this->update(['settings' => array_merge($this->settings ?? [], ['default_pages_seeded' => true])]);
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
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'rank', 'committee_role', 'member_number', 'status', 'phone', 'emergency_contact', 'dietary_notes', 'invitation_token', 'invited_at', 'invitation_accepted_at'])
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
    public function financialYearIsClosed(int $year): bool
    {
        return DB::table('accounting_period_closes')
            ->where('club_id', $this->id)
            ->where('financial_year', $year)
            ->exists();
    }
}
