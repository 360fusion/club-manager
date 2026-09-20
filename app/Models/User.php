<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable as StripeBillable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Paddle\Billable as PaddleBillable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'password', 'is_super_admin'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['role', 'rank', 'committee_role', 'home_club_name', 'home_club_number', 'member_number', 'status', 'phone', 'emergency_contact', 'dietary_notes', 'invitation_token', 'invited_at', 'invitation_accepted_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
