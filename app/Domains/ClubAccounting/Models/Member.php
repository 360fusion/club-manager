<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MemberAccountStatus;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Observers\MemberObserver;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[ObservedBy(MemberObserver::class)]
class Member extends Model
{
    /**
     * The masonic ranks a member can hold, as stored value => label for a dropdown. A member's title (Bro, WBro,
     * and so on) is not entered: it is their rank, worked out by title() below, so the two can never disagree.
     */
    public const MASONIC_RANKS = [
        'Bro' => 'Bro (Master Mason / EA / FC)',
        'WBro' => 'WBro (Worshipful Brother / PM)',
        'VWBro' => 'VWBro (Very Worshipful)',
        'RWBro' => 'RWBro (Right Worshipful)',
        'MWBro' => 'MWBro (Most Worshipful)',
    ];

    protected $table = 'club_acc_members';

    protected $fillable = [
        'club_id',
        'user_id',
        'customer_account_id',
        'subscription_tier_id',
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
        'left_on',
    ];

    /**
     * A member who resigns, is excluded or dies is stamped with the day it was recorded (unless a date was given), and
     * the stamp goes again if they come back, so the annual return can tell who left during the year.
     */
    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            if (! $member->isDirty('membership_status')) {
                return;
            }

            if ($member->membership_status->hasLeft()) {
                $member->left_on ??= now()->toDateString();
            } else {
                $member->left_on = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'membership_status' => MembershipStatus::class,
            'current_office' => LodgeOffice::class,
            'date_of_initiation' => 'date',
            'date_of_passing' => 'date',
            'date_of_raising' => 'date',
            'date_of_joining' => 'date',
            'left_on' => 'date',
            'annual_dues_override' => 'decimal:2',
            'passed_on_prefs' => 'array',
        ];
    }

    // Accessors

    /**
     * The active member holding an office in the lodge (Secretary, Worshipful Master...), or null when nobody does.
     */
    public static function holdingOffice(Club $club, LodgeOffice $office): ?self
    {
        return self::where('club_id', $club->id)->active()->where('current_office', $office->value)->with('user')->first();
    }

    /**
     * Who signs the summons and takes replies: the member holding the Secretary office (with their rank), else the
     * account given the Secretary role. Null when the lodge has neither, so nobody is named by mistake.
     *
     * @return array{name: string, email: ?string}|null
     */
    public static function summonsSecretary(Club $club): ?array
    {
        if ($secretary = self::holdingOffice($club, LodgeOffice::Secretary)) {
            return ['name' => $secretary->formatted_rank_name, 'email' => $secretary->email ?: $secretary->user?->email];
        }

        $user = $club->users()->wherePivotIn('role', [ClubRole::Coach->value, ClubRole::Secretary->value])->wherePivot('status', 'active')->first();

        return $user ? ['name' => $user->name, 'email' => $user->email] : null;
    }

    /**
     * The member's title, taken from their masonic rank. The old stored `title` column is no longer read.
     * For a name to show on screen use formatted_rank_name; for the plain name use full_name.
     */
    public function getTitleAttribute(): string
    {
        return $this->masonic_rank ? trim($this->masonic_rank) : 'Bro';
    }

    public function getFullNameAttribute(): string
    {
        $firstName = ! empty($this->preferred_name) ? trim($this->preferred_name) : $this->first_name;
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
        $rankPrefix = $this->title;
        $name = $this->full_name;
        $suffixes = array_filter([$this->grand_rank, $this->provincial_rank]);
        $suffixStr = ! empty($suffixes) ? ' '.implode(', ', $suffixes) : '';

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
    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('membership_status', [
            MembershipStatus::Active->value,
            MembershipStatus::Honorary->value,
            MembershipStatus::Historical->value,
        ]);
    }

    /**
     * Members who are billed dues (Honorary members get a waived line), unlike active(), which also lists Historical members.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeSubscribing(Builder $query): Builder
    {
        return $query->whereIn('membership_status', array_map(
            fn (MembershipStatus $status) => $status->value,
            array_filter(MembershipStatus::cases(), fn (MembershipStatus $status) => $status->isSubscribing()),
        ));
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeOfficers(Builder $query): Builder
    {
        return $query->where('current_office', '!=', LodgeOffice::Member->value);
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopePastMasters(Builder $query): Builder
    {
        return $query->whereIn('masonic_rank', ['WBro', 'VWBro', 'RWBro', 'MWBro']);
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim($search))) {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->whereLike('first_name', $term)
                ->orWhereLike('last_name', $term)
                ->orWhereLike('email', $term)
                ->orWhereLike('grand_lodge_number', $term)
                ->orWhereLike('provincial_rank', $term)
                ->orWhereLike('grand_rank', $term);
        });
    }

    /**
     * Adds the member's portal-account columns (from club_user and users) as subselects, so a list can show each
     * member's account status without a query per row.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeWithAccountState(Builder $query, int $clubId): Builder
    {
        $pivot = fn (string $expression) => DB::table('club_user')
            ->selectRaw($expression)
            ->whereColumn('club_user.user_id', 'club_acc_members.user_id')
            ->where('club_user.club_id', $clubId)
            ->limit(1);

        return $query->select('club_acc_members.*')->addSelect([
            'acct_pivot_status' => $pivot('club_user.status'),
            'acct_has_token' => $pivot('club_user.invitation_token is not null'),
            'acct_invited_at' => $pivot('club_user.invited_at'),
            'acct_accepted_at' => $pivot('club_user.invitation_accepted_at'),
            'acct_verified' => DB::table('users')
                ->selectRaw('users.email_verified_at is not null')
                ->whereColumn('users.id', 'club_acc_members.user_id')
                ->limit(1),
        ]);
    }

    /**
     * Restricts to members in one account state: 'not_invited', 'invited' (any outstanding invite or join request)
     * or 'has_account'.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeWhereAccountState(Builder $query, string $state, int $clubId): Builder
    {
        $hasAccount = fn ($q) => $q->whereExists(fn ($sub) => $sub->from('club_user')
            ->whereColumn('club_user.user_id', 'club_acc_members.user_id')
            ->where('club_user.club_id', $clubId)
            ->where('club_user.status', ClubMemberStatus::Active->value)
            ->where(fn ($w) => $w->whereNotNull('club_user.invitation_accepted_at')
                ->orWhereExists(fn ($u) => $u->from('users')
                    ->whereColumn('users.id', 'club_acc_members.user_id')
                    ->whereNotNull('users.email_verified_at'))));

        $pending = fn ($q) => $q->whereExists(fn ($sub) => $sub->from('club_user')
            ->whereColumn('club_user.user_id', 'club_acc_members.user_id')
            ->where('club_user.club_id', $clubId)
            ->where(fn ($w) => $w->where('club_user.status', ClubMemberStatus::Pending->value)
                ->orWhere(fn ($t) => $t->where('club_user.status', ClubMemberStatus::Active->value)->whereNotNull('club_user.invitation_token'))));

        return match ($state) {
            'has_account' => $query->where($hasAccount),
            'invited' => $query->where($pending)->whereNot($hasAccount),
            'not_invited' => $query->whereNot($hasAccount)->whereNot($pending),
            default => $query,
        };
    }

    /**
     * The member's portal-account state. Uses the columns from scopeWithAccountState() when they were loaded.
     */
    public function accountStatus(?Club $club = null): MemberAccountStatus
    {
        $club ??= $this->club;

        if (! array_key_exists('acct_pivot_status', $this->attributes)) {
            $row = $this->user_id
                ? DB::table('club_user')->where('club_id', $this->club_id)->where('user_id', $this->user_id)->first()
                : null;

            return MemberAccountStatus::resolve(
                $row?->status,
                ! empty($row?->invitation_token),
                $row?->invited_at ? Carbon::parse($row->invited_at) : null,
                $row?->invitation_accepted_at ? Carbon::parse($row->invitation_accepted_at) : null,
                $this->user_id ? (bool) DB::table('users')->where('id', $this->user_id)->whereNotNull('email_verified_at')->exists() : false,
                $club->inviteExpirationDays(),
            );
        }

        return MemberAccountStatus::resolve(
            $this->attributes['acct_pivot_status'],
            (bool) $this->attributes['acct_has_token'],
            $this->attributes['acct_invited_at'] ? Carbon::parse($this->attributes['acct_invited_at']) : null,
            $this->attributes['acct_accepted_at'] ? Carbon::parse($this->attributes['acct_accepted_at']) : null,
            (bool) $this->attributes['acct_verified'],
            $club->inviteExpirationDays(),
        );
    }

    // Relationships
    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AccountingContact, $this>
     */
    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingContact::class, 'customer_account_id');
    }

    /**
     * @return BelongsTo<SubscriptionTier, $this>
     */
    public function subscriptionTier(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    /**
     * @return HasMany<MemberSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'member_id');
    }

    /**
     * Whether deleting this member would lose lodge records: dues, offices held, festival giving, or the candidate
     * record they were initiated from. Such a member is marked as resigned, deceased or historical instead.
     */
    public function hasLodgeHistory(): bool
    {
        return $this->subscriptions()->exists()
            || $this->annualAssignments()->exists()
            || MemberFestivalGiving::where('member_id', $this->id)->exists()
            || Candidate::where('converted_member_id', $this->id)->exists();
    }

    /**
     * @return HasMany<AnnualOfficerAssignment, $this>
     */
    public function annualAssignments(): HasMany
    {
        return $this->hasMany(AnnualOfficerAssignment::class, 'member_id');
    }

    /**
     * Set by loadActiveOffices() for a whole list of members; looked up for this one member otherwise.
     *
     * @var list<string>|null
     */
    private ?array $loadedOffices = null;

    /**
     * Work out the offices of every member in a list with two queries, instead of two per member.
     *
     * @param  iterable<Member>  $members
     */
    public static function loadActiveOffices(iterable $members): void
    {
        $members = collect($members);

        if ($members->isEmpty()) {
            return;
        }

        $latestRosterByClub = AnnualOfficerRoster::whereIn('club_id', $members->pluck('club_id')->unique())
            ->where('status', 'confirmed')
            ->orderBy('id')
            ->get()
            ->groupBy('club_id')
            ->map(fn ($rosters) => $rosters->last()->id);

        $assignments = AnnualOfficerAssignment::whereIn('roster_id', $latestRosterByClub->values())
            ->whereIn('member_id', $members->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($assignment) => $assignment->roster_id.':'.$assignment->member_id);

        foreach ($members as $member) {
            $rosterId = $latestRosterByClub->get($member->club_id);
            $member->loadedOffices = $member->officesFrom($rosterId ? $assignments->get($rosterId.':'.$member->id, collect()) : null);
        }
    }

    /**
     * Whether this user holds one of these offices in the lodge (roster or member record), whatever their login role.
     *
     * @param  list<LodgeOffice>  $offices
     */
    public static function userHoldsOffice(Club $club, ?User $user, array $offices): bool
    {
        $member = $user ? self::where('club_id', $club->id)->where('user_id', $user->id)->active()->first() : null;
        if (! $member) {
            return false;
        }

        self::loadActiveOffices([$member]);

        return collect($member->active_offices)
            ->map(fn ($office) => $office instanceof LodgeOffice ? $office : LodgeOffice::tryFrom((string) $office))
            ->contains(fn (?LodgeOffice $office) => in_array($office, $offices, true));
    }

    /**
     * The lodge's officers as its own records have them (the confirmed roster, else each member's office), in the
     * order a summons lists them, leaving out plain members and committee members.
     *
     * @return list<array{role: string, name: string}>
     */
    public static function currentOfficers(Club $club): array
    {
        $members = self::where('club_id', $club->id)->active()->get();
        self::loadActiveOffices($members);
        $order = array_flip(array_column(LodgeOffice::cases(), 'value'));

        return $members
            ->flatMap(fn (Member $member) => collect($member->active_offices)
                ->map(fn ($office) => $office instanceof LodgeOffice ? $office : LodgeOffice::tryFrom((string) $office))
                ->filter(fn (?LodgeOffice $office) => $office && ! in_array($office, [LodgeOffice::Member, LodgeOffice::CommitteeMember], true))
                ->map(fn (LodgeOffice $office) => ['order' => $order[$office->value], 'role' => $office->label(), 'name' => $member->formatted_rank_name]))
            ->sortBy('order')
            ->map(fn (array $row) => ['role' => $row['role'], 'name' => $row['name']])
            ->values()
            ->all();
    }

    /**
     * @return list<string|LodgeOffice> an office from a roster is its code, one from the member record is the enum
     */
    public function getActiveOfficesAttribute(): array
    {
        if ($this->loadedOffices !== null) {
            return $this->loadedOffices;
        }

        $latestRoster = AnnualOfficerRoster::where('club_id', $this->club_id)
            ->where('status', 'confirmed')
            ->orderBy('id', 'desc')
            ->first();

        return $this->officesFrom($latestRoster
            ? AnnualOfficerAssignment::where('roster_id', $latestRoster->id)->where('member_id', $this->id)->orderBy('id')->get()
            : null);
    }

    /**
     * The offices on the latest confirmed roster, or the office on the member's own record when there is no roster
     * or they are not on it.
     *
     * @param  Collection<int, AnnualOfficerAssignment>|null  $assignments  null when the club has no confirmed roster
     * @return list<string|LodgeOffice>
     */
    private function officesFrom(?Collection $assignments): array
    {
        if ($assignments === null || $assignments->isEmpty()) {
            return [$this->current_office];
        }

        return $assignments->map(fn ($a) => $a->lodge_office)->filter()->values()->all();
    }
}
