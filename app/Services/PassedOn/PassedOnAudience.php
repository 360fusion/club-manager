<?php

namespace App\Services\PassedOn;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Models\User;
use App\Support\PassedOnPreferences;
use Illuminate\Support\Collection;

/**
 * Who gets a passed-on item, and how: the sending lodge's members (with an account, or on the roll with only an
 * email) as each chose for that kind, less anyone who muted the lodge; for a lodge's summons, also the lodge's
 * followers who asked to hear about its summonses (in the app only); and never anyone who already had the same
 * meeting or circular in the last 60 days.
 */
class PassedOnAudience
{
    public const REPEAT_DAYS = 60;

    public function __construct(private LodgeLinks $links) {}

    /**
     * Each row: user (or null), member (a roster member with no account, or null), email (when emailed), kind,
     * via_club_id, lodge_id, follower, bell, emailed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recipients(PassedOnItem $item): Collection
    {
        if ($item->source === PassedOnItem::PLATFORM) {
            return $this->withoutRepeats($this->listingLodgesMembers($item), $item->dedupe_key);
        }

        $lodge = $item->lodge;
        // A lodge on the platform already tells its own members, and decides itself what its followers see.
        $ownMembers = $lodge?->isManaged() && $lodge->club ? $this->activeUserIds($lodge->club) : [];
        $rows = $this->lodgeMembers($item->club, (string) $item->kind, $item->lodge_id, $ownMembers === [] ? null : fn (User $user) => ! in_array($user->id, $ownMembers, true));

        if ($item->type === PassedOnItem::SUMMONS && $lodge && ! $lodge->isManaged()) {
            $rows = $rows->concat($this->followers($item, $rows));
        }

        return $this->withoutRepeats($rows, $item->dedupe_key);
    }

    /**
     * A lodge's members as they chose for $kind. `$canSee` leaves out people who may not see what is sent, and
     * `$rosterToo` whether members with no account (who are only emailed) are included.
     *
     * @param  (callable(User): bool)|null  $canSee
     * @return Collection<int, array<string, mixed>>
     */
    public function lodgeMembers(Club $club, string $kind, ?int $lodgeId, ?callable $canSee = null, bool $rosterToo = true): Collection
    {
        $users = $club->users()
            ->wherePivot('status', ClubMemberStatus::Active->value)
            ->wherePivot('role', '!=', ClubRole::Visitor->value)
            ->get()
            ->filter(fn (User $user) => $canSee === null || $canSee($user));

        $rows = $users->map(fn (User $user) => $this->row(PassedOnPreferences::fromArray($user->passed_on_prefs), $kind, $lodgeId, $club, user: $user, email: $user->email))->reject(fn (?array $row) => $row === null);

        if ($rosterToo) {
            $emails = $users->pluck('email')->filter()->map(fn (string $email) => mb_strtolower($email))->all();
            $roster = Member::where('club_id', $club->id)->whereIn('membership_status', [MembershipStatus::Active->value, MembershipStatus::Honorary->value])->whereNull('user_id')->whereNotNull('email')->where('email', '!=', '')->get()
                ->reject(fn (Member $member) => in_array(mb_strtolower($member->email), $emails, true))
                ->map(fn (Member $member) => $this->row(PassedOnPreferences::fromArray($member->passed_on_prefs, hasAccount: false), $kind, $lodgeId, $club, member: $member, email: $member->email))
                ->reject(fn (?array $row) => $row === null);
            $rows = $rows->concat($roster);
        }

        return $rows->values();
    }

    /**
     * A platform lodge's own summons (only lodges that share with anyone send one): the members of every lodge that
     * lists it, under that list, except its own members, who had their summons.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function listingLodgesMembers(PassedOnItem $item): Collection
    {
        $ownMembers = $this->activeUserIds($item->club);
        $notOwn = fn (User $user) => ! in_array($user->id, $ownMembers, true);

        return $this->links->clubsListing($item->lodge)
            ->flatMap(fn (array $listing) => $this->lodgeMembers($listing['club'], $listing['relation'], $item->lodge_id, $notOwn))
            ->values();
    }

    /**
     * @return list<int>
     */
    private function activeUserIds(Club $club): array
    {
        return $club->users()->wherePivot('status', ClubMemberStatus::Active->value)->pluck('users.id')->all();
    }

    /**
     * People following the item's lodge who asked to hear about its summonses, and are not already getting it.
     *
     * @param  Collection<int, array<string, mixed>>  $already
     * @return Collection<int, array<string, mixed>>
     */
    private function followers(PassedOnItem $item, Collection $already): Collection
    {
        $have = $already->pluck('user.id')->filter()->all();

        return $item->lodge->followers()->wherePivot('notify_summons', true)->get()
            ->reject(fn (User $user) => in_array($user->id, $have, true))
            ->reject(fn (User $user) => PassedOnPreferences::fromArray($user->passed_on_prefs)->mutes($item->lodge_id))
            ->map(fn (User $user) => $this->followerRow($user, $item))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function followerRow(User $user, PassedOnItem $item): array
    {
        return [
            'user' => $user, 'member' => null, 'email' => null, 'kind' => (string) $item->kind, 'via_club_id' => $item->club_id,
            'lodge_id' => $item->lodge_id, 'follower' => true, 'bell' => true, 'emailed' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(PassedOnPreferences $prefs, string $kind, ?int $lodgeId, Club $club, ?User $user = null, ?Member $member = null, ?string $email = null): ?array
    {
        if ($prefs->mutes($lodgeId)) {
            return null;
        }

        $channels = $prefs->channels($kind);
        $bell = $user !== null && in_array('app', $channels, true);
        $emailed = filled($email) && in_array('email', $channels, true);

        return $bell || $emailed ? [
            'user' => $user, 'member' => $member, 'email' => $emailed ? $email : null, 'kind' => $kind, 'via_club_id' => $club->id,
            'lodge_id' => $lodgeId, 'follower' => false, 'bell' => $bell, 'emailed' => $emailed,
        ] : null;
    }

    /**
     * One copy per person: a person in two of the lodges is counted once, an email address on several rolls is
     * written to once, and anyone who already had this meeting or circular lately is left out.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function withoutRepeats(Collection $rows, string $dedupeKey): Collection
    {
        $earlier = PassedOnDelivery::where('dedupe_key', $dedupeKey)->where('created_at', '>=', now()->subDays(self::REPEAT_DAYS))->get(['user_id', 'member_id', 'email']);
        $earlierUsers = $earlier->pluck('user_id')->filter()->all();
        $earlierAddresses = $earlier->pluck('email')->filter()
            ->concat($earlierUsers === [] ? [] : User::whereIn('id', $earlierUsers)->pluck('email'))
            ->map(fn (string $email) => mb_strtolower($email))->all();
        $addressesNow = $rows->filter(fn (array $row) => $row['user'] !== null)->map(fn (array $row) => mb_strtolower((string) $row['user']->email))->all();

        return $rows
            ->reject(fn (array $row) => $row['user'] !== null && in_array($row['user']->id, $earlierUsers, true))
            ->reject(fn (array $row) => $row['user'] === null && (in_array(mb_strtolower((string) $row['email']), $earlierAddresses, true) || in_array(mb_strtolower((string) $row['email']), $addressesNow, true)))
            ->unique(fn (array $row) => $row['user'] ? 'u'.$row['user']->id : 'e'.mb_strtolower((string) $row['email']))
            ->values();
    }
}
