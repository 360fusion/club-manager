<?php

namespace App\Services\Lodges;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Models\LodgeClaimEvent;
use App\Models\RecurringRule;
use App\Models\User;
use App\Notifications\LodgeClaimNotification;
use App\Support\Months;
use App\Support\ReservedClubSlugs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Everything that can happen to a lodge claim. Each step checks the claim is in the right state,
 * changes it inside a transaction and writes a line of history, so no other code needs to.
 */
class LodgeClaimService
{
    /**
     * @param  array{claimant_role: string, message: string, phone?: ?string, evidence?: ?string}  $data
     */
    public function submit(Lodge $lodge, User $user, array $data): LodgeClaim
    {
        if ($lodge->status !== 'active' || $lodge->isManaged()) {
            throw ValidationException::withMessages(['lodge' => 'This lodge cannot be claimed. If someone already manages it, ask them for access.']);
        }

        if ($lodge->claims()->where('user_id', $user->id)->open()->exists()) {
            throw ValidationException::withMessages(['lodge' => 'You already have a claim for this lodge waiting to be checked.']);
        }

        if (LodgeClaim::where('user_id', $user->id)->open()->count() >= LodgeClaim::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['lodge' => 'You have '.LodgeClaim::MAX_OPEN_PER_USER.' claims waiting to be checked. Wait for a decision, or withdraw one, before making another.']);
        }

        $claim = DB::transaction(function () use ($lodge, $user, $data) {
            $claim = $lodge->claims()->create([...$data, 'user_id' => $user->id, 'status' => LodgeClaim::PENDING]);
            $this->log($claim, $user, 'submitted', $data['message']);

            return $claim;
        });

        $this->tellSuperAdmins(LodgeClaimNotification::submitted($claim->load(['lodge', 'user'])));

        return $claim;
    }

    public function requestInfo(LodgeClaim $claim, User $admin, string $question): void
    {
        $this->guardOpen($claim, [LodgeClaim::PENDING]);

        DB::transaction(function () use ($claim, $admin, $question) {
            $claim->update(['status' => LodgeClaim::MORE_INFO]);
            $this->log($claim, $admin, 'more_info_requested', $question);
        });

        $claim->load(['lodge', 'user'])->user->notify(LodgeClaimNotification::moreInfoNeeded($claim, $question));
    }

    public function respond(LodgeClaim $claim, User $user, string $reply): void
    {
        abort_unless($claim->user_id === $user->id, 404);
        $this->guardOpen($claim, [LodgeClaim::MORE_INFO]);

        DB::transaction(function () use ($claim, $user, $reply) {
            $claim->update(['status' => LodgeClaim::PENDING]);
            $this->log($claim, $user, 'info_provided', $reply);
        });

        $this->tellSuperAdmins(LodgeClaimNotification::replied($claim->load(['lodge', 'user'])));
    }

    public function withdraw(LodgeClaim $claim, User $user): void
    {
        abort_unless($claim->user_id === $user->id, 404);
        $this->guardOpen($claim);

        DB::transaction(function () use ($claim, $user) {
            $this->settle($claim, LodgeClaim::WITHDRAWN);
            $this->log($claim, $user, 'withdrawn');
        });
    }

    public function reject(LodgeClaim $claim, User $admin, string $reason): void
    {
        $this->guardOpen($claim);

        DB::transaction(function () use ($claim, $admin, $reason) {
            $this->settle($claim, LodgeClaim::REJECTED, $admin, $reason);
            $this->log($claim, $admin, 'rejected', $reason);
        });

        $claim->load(['lodge', 'user'])->user->notify(LodgeClaimNotification::rejected($claim));
    }

    /**
     * Approve by giving the claimant a new club that runs the lodge.
     */
    public function approveWithNewClub(LodgeClaim $claim, User $admin, ?string $note = null): Club
    {
        $club = DB::transaction(function () use ($claim, $admin, $note) {
            $lodge = $this->lockForApproval($claim);

            $club = Club::create([
                'club_type_id' => $lodge->club_type_id,
                'province_id' => $lodge->province_id,
                'masonic_hall_id' => $lodge->masonic_hall_id,
                'name' => $lodge->displayName(),
                'slug' => $this->uniqueClubSlug($lodge),
                'lodge_number' => $lodge->number,
                'town_city' => $lodge->masonicHall?->town,
                'province_region' => $lodge->province?->name,
                'status' => 'active',
                'settings' => array_filter(['installation_month' => Months::name($lodge->installation_month)]),
            ]);

            $club->users()->attach($claim->user_id, ['role' => 'owner', 'status' => 'active']);
            $this->copyMeetingPattern($lodge, $club);
            $this->finishApproval($claim, $lodge, $club, $admin, 'approved', $note);

            return $club;
        });

        $claim->load(['lodge', 'user', 'club'])->user->notify(LodgeClaimNotification::approved($claim));

        return $club;
    }

    /**
     * Approve by linking the listing to a club that already exists. Nobody is given access by
     * this unless $makeClaimantAdmin is set, because the club already has its own people.
     */
    public function approveByLinking(LodgeClaim $claim, User $admin, Club $club, bool $makeClaimantAdmin = false, ?string $note = null): void
    {
        DB::transaction(function () use ($claim, $admin, $club, $makeClaimantAdmin, $note) {
            $lodge = $this->lockForApproval($claim);

            if (Lodge::where('club_id', $club->id)->exists()) {
                throw ValidationException::withMessages(['club_id' => 'That club already manages another listing.']);
            }

            if ($makeClaimantAdmin && ! $club->users()->whereKey($claim->user_id)->exists()) {
                $club->users()->attach($claim->user_id, ['role' => 'admin', 'status' => 'active']);
            }

            $this->finishApproval($claim, $lodge, $club, $admin, 'linked', $note);
        });

        $claim->load(['lodge', 'user', 'club'])->user->notify(LodgeClaimNotification::approved($claim));
    }

    /**
     * Link a listing to an existing club without a claim (a superadmin doing it directly).
     */
    public function link(Lodge $lodge, Club $club): void
    {
        if ($lodge->isManaged() || Lodge::where('club_id', $club->id)->exists()) {
            throw ValidationException::withMessages(['club_id' => 'That listing or club is already linked.']);
        }

        DB::transaction(function () use ($lodge, $club) {
            $lodge->forceFill(['club_id' => $club->id, 'claimed_at' => now()])->save();

            foreach ($lodge->claims()->open()->get() as $other) {
                $this->settle($other, LodgeClaim::SUPERSEDED, null, 'The listing was linked to an existing club.');
                $this->log($other, null, 'superseded', 'The listing was linked to an existing club.');
            }
        });
    }

    /**
     * Take a listing back from its club (the club and its data are left alone).
     */
    public function unlink(Lodge $lodge, User $admin): void
    {
        abort_unless($lodge->isManaged(), 422);

        DB::transaction(function () use ($lodge, $admin) {
            $approved = $lodge->claims()->where('club_id', $lodge->club_id)->where('status', LodgeClaim::APPROVED)->latest('id')->first();

            if ($approved) {
                $this->log($approved, $admin, 'unlinked', 'The listing was taken back from '.$lodge->club->name.'.');
            }

            $lodge->forceFill(['club_id' => null, 'claimed_at' => null])->save();
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function guardOpen(LodgeClaim $claim, array $allowed = LodgeClaim::OPEN): void
    {
        if (! in_array($claim->fresh()->status, $allowed, true)) {
            throw ValidationException::withMessages(['claim' => 'This claim has already been dealt with.']);
        }
    }

    private function lockForApproval(LodgeClaim $claim): Lodge
    {
        $claim = LodgeClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
        $lodge = Lodge::whereKey($claim->lodge_id)->lockForUpdate()->with(['masonicHall', 'province', 'clubType', 'schedules'])->firstOrFail();

        if (! $claim->isOpen()) {
            throw ValidationException::withMessages(['claim' => 'This claim has already been dealt with.']);
        }

        if ($lodge->isManaged()) {
            throw ValidationException::withMessages(['claim' => 'This lodge is already managed by a club.']);
        }

        return $lodge;
    }

    private function finishApproval(LodgeClaim $claim, Lodge $lodge, Club $club, User $admin, string $eventType, ?string $note): void
    {
        $lodge->forceFill(['club_id' => $club->id, 'claimed_at' => now()])->save();

        $this->settle($claim, LodgeClaim::APPROVED, $admin, $note, $club);
        $this->log($claim, $admin, $eventType, $note);

        foreach ($lodge->claims()->open()->where('id', '!=', $claim->id)->get() as $other) {
            $this->settle($other, LodgeClaim::SUPERSEDED, $admin, 'Another claim for this lodge was approved.');
            $this->log($other, $admin, 'superseded', 'Another claim for this lodge was approved.');
            $other->load(['lodge', 'user'])->user->notify(LodgeClaimNotification::rejected($other));
        }
    }

    private function copyMeetingPattern(Lodge $lodge, Club $club): void
    {
        $schedule = $lodge->schedules->first();

        if (! $schedule) {
            return;
        }

        RecurringRule::create([
            'club_id' => $club->id,
            'name' => 'Regular meeting',
            'occurrence' => $schedule->occurrence,
            'day_of_week' => $schedule->day_of_week,
            'active_months' => $schedule->months,
            'default_start_time' => ($schedule->start_time ?? '18:30').':00',
            'default_venue' => $lodge->masonicHall?->name,
        ]);
    }

    private function uniqueClubSlug(Lodge $lodge): string
    {
        $base = Str::slug($lodge->displayName()) ?: 'lodge';
        $candidates = [$base, trim($base.'-'.Str::slug((string) $lodge->number), '-')];

        foreach ($candidates as $slug) {
            if (! ReservedClubSlugs::isReserved($slug) && ! Club::where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        for ($i = 2; ; $i++) {
            $slug = $candidates[1].'-'.$i;

            if (! Club::where('slug', $slug)->exists()) {
                return $slug;
            }
        }
    }

    /**
     * Change a claim's status and the fields that record who decided and why. These are not
     * mass-assignable on purpose, so nothing but this service can set them.
     */
    private function settle(LodgeClaim $claim, string $status, ?User $by = null, ?string $note = null, ?Club $club = null): void
    {
        $claim->forceFill([
            'status' => $status,
            'decided_by' => $by?->id,
            'decided_at' => now(),
            'decision_note' => $note,
            'club_id' => $club?->id ?? $claim->club_id,
        ])->save();
    }

    private function log(LodgeClaim $claim, ?User $actor, string $type, ?string $note = null): void
    {
        LodgeClaimEvent::create(['lodge_claim_id' => $claim->id, 'actor_id' => $actor?->id, 'type' => $type, 'note' => $note]);
    }

    private function tellSuperAdmins(LodgeClaimNotification $notification): void
    {
        Notification::send(User::where('is_super_admin', true)->get(), $notification);
    }
}
