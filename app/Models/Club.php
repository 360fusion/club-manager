<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Laravel\Cashier\Billable as StripeBillable;
use Laravel\Paddle\Billable as PaddleBillable;

class Club extends Model
{
    use HasFactory;
    use StripeBillable, PaddleBillable {
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
        'name',
        'slug',
        'logo_url',
        'custom_domain',
        'domain_status',
        'domain_verified_at',
        'settings',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function clubType(): BelongsTo
    {
        return $this->belongsTo(ClubType::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'member_number', 'status'])
            ->withTimestamps();
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

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
