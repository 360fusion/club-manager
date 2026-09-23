<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MemberAccountStatus;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one place an online-account invitation is created, resent, reminded or revoked, whether it starts from the
 * Members directory, a bulk action, a CSV import or the Users page. A member is tied to a portal account through
 * `club_acc_members.user_id` and the user's `club_user` row.
 */
class MemberInvitationService
{
    public const RESEND_COOLDOWN_MINUTES = 10;

    public const BULK_LIMIT = 200;

    /**
     * Invite a member to set up an account. Refuses with a plain-language reason when they cannot be invited.
     *
     * @return array{url: string, emailed: bool}
     *
     * @throws MemberInvitationException
     */
    public function invite(Member $member, Club $club, ?User $actor = null): array
    {
        $this->assertInvitable($member, $club);

        $email = strtolower(trim((string) $member->email));
        $token = Str::random(40);

        $user = DB::transaction(function () use ($member, $club, $actor, $email, $token) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $member->full_name, 'password' => Hash::make(Str::random(32))],
            );

            $clash = Member::where('club_id', $club->id)->where('user_id', $user->id)->where('id', '!=', $member->id)->first();

            if ($clash) {
                throw new MemberInvitationException("That email already belongs to {$clash->full_name}'s account.");
            }

            $fields = [
                'invitation_token' => $token,
                'invited_at' => now(),
                'invited_by' => $actor?->id,
                'invitation_reminded_at' => null,
                'invitation_accepted_at' => null,
            ];

            $pivot = $club->users()->where('users.id', $user->id)->first()?->pivot;

            if (! $pivot) {
                $club->users()->attach($user->id, $fields + [
                    'role' => 'member',
                    'status' => 'pending',
                    'member_number' => $member->grand_lodge_number ?: 'MEM-'.random_int(1000, 9999),
                ]);
            } else {
                // Someone already active at the club keeps their access and role; anyone else waits as pending.
                $club->users()->updateExistingPivot($user->id, $fields + ($pivot->status === 'active' ? [] : ['status' => 'pending']));
            }

            $member->forceFill(['user_id' => $user->id])->save();

            return $user;
        });

        return $this->send($club, $user, $token, $actor);
    }

    /**
     * Send a fresh link to someone whose invitation is still outstanding (or has run out).
     *
     * @return array{url: string, emailed: bool}
     *
     * @throws MemberInvitationException
     */
    public function resend(Member $member, Club $club, ?User $actor = null): array
    {
        $this->assertSameClub($member, $club);

        $pivot = $this->pivotFor($member, $club);

        if (! $pivot || empty($pivot->invitation_token) || $member->accountStatus($club) === MemberAccountStatus::HasAccount) {
            throw new MemberInvitationException('There is no outstanding invitation to resend.');
        }

        if ($pivot->invited_at && now()->diffInMinutes($pivot->invited_at, true) < self::RESEND_COOLDOWN_MINUTES) {
            throw new MemberInvitationException('An invitation was sent a moment ago. Please wait a few minutes before sending another.');
        }

        $token = Str::random(40);

        $club->users()->updateExistingPivot($member->user_id, [
            'invitation_token' => $token,
            'invited_at' => now(),
            'invited_by' => $actor?->id ?? $pivot->invited_by,
            'invitation_reminded_at' => null,
        ]);

        return $this->send($club, User::findOrFail($member->user_id), $token, $actor);
    }

    /**
     * Nudge someone whose invitation has not been answered. Reuses the same link, so the earlier email still works.
     */
    public function remind(Club $club, User $user): bool
    {
        $pivot = $club->users()->where('users.id', $user->id)->first()?->pivot;

        if (! $pivot || ! in_array($pivot->status, ['pending', 'active'], true) || empty($pivot->invitation_token) || $pivot->invitation_accepted_at || ($pivot->status === 'active' && $user->hasVerifiedEmail())) {
            return false;
        }

        $club->users()->updateExistingPivot($user->id, ['invitation_reminded_at' => now()]);

        $inviter = $pivot->invited_by ? User::find($pivot->invited_by) : null;

        return $this->send($club, $user, $pivot->invitation_token, $inviter, reminder: true)['emailed'];
    }

    /**
     * Withdraw an outstanding invitation. Someone invited who never had an account is removed from the club again.
     */
    public function revoke(Club $club, User $user): void
    {
        $pivot = $club->users()->where('users.id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return;
        }

        $neverHadAccount = $pivot->status === 'pending'
            && $user->email_verified_at === null
            && $user->clubs()->where('clubs.id', '!=', $club->id)->doesntExist();

        if ($neverHadAccount) {
            $club->users()->detach($user->id);
            Member::where('club_id', $club->id)->where('user_id', $user->id)->update(['user_id' => null]);

            return;
        }

        $club->users()->updateExistingPivot($user->id, [
            'invitation_token' => null,
            'invited_at' => null,
            'invitation_reminded_at' => null,
        ]);
    }

    /**
     * The member's email changed: an invitation sent to the old address no longer applies.
     */
    public function emailChanged(Member $member): void
    {
        $club = $member->club;
        $user = $member->user_id ? User::find($member->user_id) : null;

        if (! $club || ! $user || ! $member->accountStatus($club)->isInvitePending()) {
            return;
        }

        $this->revoke($club, $user);
    }

    /**
     * The member has left the lodge (resigned, excluded or deceased): drop any open invitation and, for an ordinary
     * member account, end portal access. Staff roles are left for an admin to review. A deleted record only loses
     * its open invitation ($endAccess false).
     */
    public function memberLeft(Member $member, bool $endAccess = true): void
    {
        $club = $member->club;
        $user = $member->user_id ? User::find($member->user_id) : null;

        if (! $club || ! $user) {
            return;
        }

        $pivot = $club->users()->where('users.id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return;
        }

        if ($pivot->status === 'pending') {
            $this->revoke($club, $user);
        } elseif ($endAccess && $pivot->status === 'active' && $pivot->role === 'member') {
            $club->users()->updateExistingPivot($user->id, ['status' => 'past', 'invitation_token' => null]);
        }
    }

    /**
     * Invite many members at once. Ids from the browser are only trusted once found in this club.
     *
     * @param  array<int, int|string>  $memberIds
     * @return array{invited: int, skipped: array<string, int>, failed: int, capped: bool}
     */
    public function inviteMany(Club $club, array $memberIds, ?User $actor = null): array
    {
        $ids = collect($memberIds)->map(fn ($id) => (int) $id)->unique()->values();
        $capped = $ids->count() > self::BULK_LIMIT;

        $members = Member::where('club_id', $club->id)->whereIn('id', $ids->take(self::BULK_LIMIT))->get();

        $result = ['invited' => 0, 'skipped' => [], 'failed' => 0, 'capped' => $capped];

        foreach ($members as $member) {
            try {
                $sent = $this->invite($member, $club, $actor);
                $sent['emailed'] ? $result['invited']++ : $result['failed']++;
            } catch (MemberInvitationException $e) {
                $result['skipped'][$e->getMessage()] = ($result['skipped'][$e->getMessage()] ?? 0) + 1;
            }
        }

        return $result;
    }

    /**
     * The member record a newly registered user probably is: same email, not yet linked to anyone.
     */
    public function matchForUser(Club $club, User $user): ?Member
    {
        $matches = Member::where('club_id', $club->id)
            ->whereNull('user_id')
            ->whereRaw('lower(email) = ?', [strtolower($user->email)])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Tie an approved user to the member record their email matches. Only ever called once an admin has approved them.
     */
    public function linkMatchingMember(Club $club, User $user): ?Member
    {
        $member = $this->matchForUser($club, $user);
        $member?->forceFill(['user_id' => $user->id])->save();

        return $member;
    }

    /**
     * The roster record for a user of the club, made from the user's name when there is none yet.
     */
    public function memberForUser(Club $club, User $user): Member
    {
        $member = Member::where('club_id', $club->id)->where('user_id', $user->id)->first()
            ?? Member::where('club_id', $club->id)->whereNull('user_id')->whereRaw('lower(email) = ?', [strtolower($user->email)])->first();

        if ($member) {
            return $member;
        }

        $parts = explode(' ', trim($user->name), 2);

        return Member::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'email' => strtolower($user->email),
            'first_name' => $parts[0] ?: $user->name,
            'last_name' => $parts[1] ?? '',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    /**
     * The club_user row behind a member's account, if any.
     */
    public function pivotFor(Member $member, Club $club): ?object
    {
        if (! $member->user_id) {
            return null;
        }

        return $club->users()->where('users.id', $member->user_id)->first()?->pivot;
    }

    /**
     * The invited user for an emailed link, or null when the link is unknown.
     */
    public function userForToken(Club $club, string $token): ?User
    {
        return $club->users()->where('club_user.invitation_token', $token)->first();
    }

    public function isExpired(Club $club, ?object $pivot): bool
    {
        return $pivot && $pivot->invited_at && Carbon::parse($pivot->invited_at)->addDays($club->inviteExpirationDays())->isPast();
    }

    /**
     * Whether a person is stopped from being invited, and why.
     *
     * @throws MemberInvitationException
     */
    private function assertInvitable(Member $member, Club $club): void
    {
        $this->assertSameClub($member, $club);

        if (blank($member->email)) {
            throw new MemberInvitationException('No email address on file');
        }

        if (! in_array($member->membership_status, [MembershipStatus::Active, MembershipStatus::Honorary], true)) {
            throw new MemberInvitationException('Only active or honorary members can be invited');
        }

        $status = $member->accountStatus($club);

        if ($status === MemberAccountStatus::HasAccount) {
            throw new MemberInvitationException('Already has an account');
        }

        if ($status->isInvitePending()) {
            throw new MemberInvitationException('Already invited');
        }
    }

    private function assertSameClub(Member $member, Club $club): void
    {
        abort_unless($member->club_id === $club->id, 404);
    }

    /**
     * @return array{url: string, emailed: bool}
     */
    private function send(Club $club, User $user, string $token, ?User $inviter, bool $reminder = false): array
    {
        $url = route('invitation.accept', ['slug' => $club->slug, 'token' => $token]);

        try {
            Mail::to($user->email)->send(new MemberInvitationMail($club, $user, $token, $url, $inviter, $reminder));

            return ['url' => $url, 'emailed' => true];
        } catch (Throwable $e) {
            report($e);

            return ['url' => $url, 'emailed' => false];
        }
    }
}
