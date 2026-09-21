<?php

namespace App\Models;

use App\Support\OrderColours;
use App\Support\ReservedClubSlugs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    }

    /**
     * Ensure default navigation pages and standard website settings exist for this club.
     */
    public function ensureDefaultPages(): void
    {
        $settings = $this->settings ?? [];
        $settingsUpdated = false;

        if (empty($settings['website_theme'])) {
            $settings['website_theme'] = 'classic';
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

        foreach ($defaultPages as $def) {
            $existingPage = $this->pages()->where('slug', $def['slug'])->first();
            if (! $existingPage) {
                $this->pages()->create($def);
            } elseif ($def['slug'] === 'home' && $existingPage->title !== 'Home') {
                $existingPage->update(['title' => 'Home']);
            }
        }
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
}
