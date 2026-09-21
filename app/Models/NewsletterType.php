<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterType extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_external_subscribable',
        'require_approval',
        'is_mandatory',
        'require_home_club_info',
        'default_roles',
        'sender_name',
        'sender_email',
        'is_automated_digest',
        'digest_frequency',
        'digest_send_day',
        'digest_send_time',
        'include_updates',
        'include_upcoming_meetings',
        'include_upcoming_events',
        'include_news_posts',
        'inbound_email_address',
    ];

    protected function casts(): array
    {
        return [
            'is_external_subscribable' => 'boolean',
            'require_approval' => 'boolean',
            'is_mandatory' => 'boolean',
            'require_home_club_info' => 'boolean',
            'is_automated_digest' => 'boolean',
            'include_updates' => 'boolean',
            'include_upcoming_meetings' => 'boolean',
            'include_upcoming_events' => 'boolean',
            'include_news_posts' => 'boolean',
            'default_roles' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<NewsletterSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(NewsletterSubscription::class);
    }

    /**
     * @return HasMany<Newsletter, $this>
     */
    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }
}
