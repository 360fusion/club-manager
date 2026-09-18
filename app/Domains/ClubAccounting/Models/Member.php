<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    use HasFactory;

    protected $table = 'club_acc_members';

    protected $fillable = [
        'club_id',
        'user_id',
        'customer_account_id',
        'subscription_tier_id',
        'title',
        'first_name',
        'middle_names',
        'last_name',
        'preferred_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'county',
        'postcode',
        'country',
        'masonic_rank',
        'grand_rank',
        'provincial_rank',
        'grand_lodge_number',
        'membership_status',
        'current_office',
        'date_of_initiation',
        'date_of_passing',
        'date_of_raising',
        'date_of_joining',
        'annual_dues_override',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'membership_status' => MembershipStatus::class,
            'current_office' => LodgeOffice::class,
            'date_of_initiation' => 'date',
            'date_of_passing' => 'date',
            'date_of_raising' => 'date',
            'date_of_joining' => 'date',
            'annual_dues_override' => 'decimal:2',
        ];
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $firstName = !empty($this->preferred_name) ? trim($this->preferred_name) : $this->first_name;
        $nameParts = array_filter([$firstName, $this->middle_names, $this->last_name]);
        return implode(' ', $nameParts);
    }

    public function getOfficialFullNameAttribute(): string
    {
        $nameParts = array_filter([$this->first_name, $this->middle_names, $this->last_name]);
        return implode(' ', $nameParts);
    }

    public function getFormattedRankNameAttribute(): string
    {
        $rankPrefix = $this->masonic_rank ? trim($this->masonic_rank) : 'Bro';
        $name = $this->full_name;
        $suffixes = array_filter([$this->grand_rank, $this->provincial_rank]);
        $suffixStr = !empty($suffixes) ? ' ' . implode(', ', $suffixes) : '';

        return "{$rankPrefix} {$name}{$suffixStr}";
    }

    public function getIsPastMasterAttribute(): bool
    {
        return in_array($this->masonic_rank, ['WBro', 'VWBro', 'RWBro', 'MWBro']);
    }

    public function getFormattedAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->county,
            $this->postcode,
            $this->country,
        ]);

        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('membership_status', [
            MembershipStatus::Active->value,
            MembershipStatus::Honorary->value,
            MembershipStatus::Historical->value,
        ]);
    }

    public function scopeOfficers(Builder $query): Builder
    {
        return $query->where('current_office', '!=', LodgeOffice::Member->value);
    }

    public function scopePastMasters(Builder $query): Builder
    {
        return $query->whereIn('masonic_rank', ['WBro', 'VWBro', 'RWBro', 'MWBro']);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim($search))) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('grand_lodge_number', 'like', $term)
                ->orWhere('provincial_rank', 'like', $term)
                ->orWhere('grand_rank', 'like', $term);
        });
    }

    // Relationships
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingContact::class, 'customer_account_id');
    }

    public function subscriptionTier(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'member_id');
    }

    public function annualAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnnualOfficerAssignment::class, 'member_id');
    }

    public function getActiveOfficesAttribute(): array
    {
        $latestRoster = AnnualOfficerRoster::where('club_id', $this->club_id)
            ->where('status', 'confirmed')
            ->orderBy('id', 'desc')
            ->first();

        if (! $latestRoster) {
            return $this->current_office ? [$this->current_office] : [];
        }

        $assignments = AnnualOfficerAssignment::where('roster_id', $latestRoster->id)
            ->where('member_id', $this->id)
            ->get();

        if ($assignments->isEmpty()) {
            return $this->current_office ? [$this->current_office] : [];
        }

        return $assignments->map(fn ($a) => $a->lodge_office)->filter()->values()->all();
    }
}
