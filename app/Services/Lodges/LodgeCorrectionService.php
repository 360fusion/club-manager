<?php

namespace App\Services\Lodges;

use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Notifications\LodgeClaimNotification;
use App\Support\MeetingScheduleParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Everything that can happen to a suggested fix: sending it, applying it to the listing, or turning
 * it down. Applying keeps the old values on the suggestion, so no other code needs to.
 */
class LodgeCorrectionService
{
    /**
     * @param  array<string, mixed>  $changes  what the person filled in, keyed as LodgeCorrection::FIELDS
     */
    public function submit(Lodge $lodge, User $user, array $changes, ?string $note): LodgeCorrection
    {
        if ($lodge->status !== 'active') {
            throw ValidationException::withMessages(['lodge' => 'This lodge is no longer in the directory.']);
        }

        $changes = $this->onlyWhatDiffers($lodge, $changes);
        $note = trim((string) $note) ?: null;

        if ($changes === [] && $note === null) {
            throw ValidationException::withMessages(['changes' => 'Change at least one detail, or tell us what is wrong in the note.']);
        }

        if ($lodge->corrections()->where('user_id', $user->id)->open()->exists()) {
            throw ValidationException::withMessages(['lodge' => 'You already sent a fix for this lodge. It is waiting to be checked.']);
        }

        if (LodgeCorrection::where('user_id', $user->id)->open()->count() >= LodgeCorrection::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['lodge' => 'You have '.LodgeCorrection::MAX_OPEN_PER_USER.' suggestions waiting to be checked. Please wait for those before sending more.']);
        }

        return $lodge->corrections()->create(['user_id' => $user->id, 'changes' => $changes, 'note' => $note]);
    }

    /**
     * Write the chosen fields to the listing. $values are the fields the superadmin ticked, with the
     * values as they left them, which may differ from what was suggested.
     *
     * @param  array<string, mixed>  $values
     */
    public function apply(LodgeCorrection $correction, User $admin, array $values, ?string $note = null): void
    {
        unset($values['hall_text']);
        $values = array_intersect_key($values, LodgeCorrection::FIELDS);

        if ($values === []) {
            throw ValidationException::withMessages(['fields' => 'Tick at least one change to apply, or turn the suggestion down.']);
        }

        foreach ($values as $field => $value) {
            if (blank($value) && ! in_array($field, LodgeCorrection::CLEARABLE, true)) {
                throw ValidationException::withMessages([$field => LodgeCorrection::FIELDS[$field].' cannot be left empty.']);
            }

            $values[$field] = blank($value) ? null : $value;
        }

        $lodge = DB::transaction(function () use ($correction, $admin, $values, $note) {
            $locked = LodgeCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['correction' => 'This suggestion has already been dealt with.']);
            }

            $lodge = Lodge::whereKey($locked->lodge_id)->lockForUpdate()->with('schedules')->firstOrFail();
            $applied = [];

            foreach ($values as $field => $value) {
                $applied[$field] = ['from' => $lodge->{$field}, 'to' => $value];
            }

            $lodge->fill($values);

            if (array_key_exists('meets_text', $values)) {
                $installation = (new MeetingScheduleParser)->installationMonth($values['meets_text']);

                if ($installation !== null && $installation !== (int) $lodge->installation_month) {
                    $applied['installation_month'] = ['from' => $lodge->installation_month, 'to' => $installation];
                    $lodge->installation_month = $installation;
                }

                $pattern = fn ($schedule) => $schedule->only(['occurrence', 'day_of_week', 'months', 'start_time']);
                $before = $lodge->schedules->map($pattern)->values()->all();
                $lodge->save();

                if ($lodge->rebuildSchedulesFromWording('correction') === 0) {
                    throw ValidationException::withMessages(['meets_text' => 'We could not read a meeting pattern from that wording. Edit it, or untick it.']);
                }

                $applied['schedules'] = ['from' => $before, 'to' => $lodge->schedules()->get()->map($pattern)->values()->all()];
            } else {
                $lodge->save();
            }

            $this->settle($locked, LodgeCorrection::APPLIED, $admin, $note, $applied);

            return $lodge;
        });

        $this->tellAboutApplied($correction->fresh(['user']), $lodge->load('club'), array_keys($values));
    }

    public function reject(LodgeCorrection $correction, User $admin, string $reason): void
    {
        DB::transaction(function () use ($correction, $admin, $reason) {
            $locked = LodgeCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['correction' => 'This suggestion has already been dealt with.']);
            }

            $this->settle($locked, LodgeCorrection::REJECTED, $admin, $reason);
        });

        $correction->load(['lodge', 'user']);
        $name = $correction->lodge->displayName();

        $correction->user->notify(new LodgeClaimNotification(
            ClubNotification::NOTICE,
            'Your suggestion for '.$name.' was not applied',
            $reason,
            $name,
            'members.lodges',
            [],
            'See your suggestions',
        ));
    }

    /**
     * The suggested values that differ from the listing. An empty box is ignored, except for the
     * fields that can be removed, where it asks for the value to go.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function onlyWhatDiffers(Lodge $lodge, array $changes): array
    {
        $kept = [];

        foreach (array_intersect_key($changes, LodgeCorrection::FIELDS) as $field => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                if (in_array($field, LodgeCorrection::CLEARABLE, true) && filled($lodge->{$field})) {
                    $kept[$field] = null;
                }

                continue;
            }

            if ($field !== 'hall_text' && (string) $lodge->{$field} === (string) $value) {
                continue;
            }

            $kept[$field] = $field === 'masonic_hall_id' ? (int) $value : $value;
        }

        return $kept;
    }

    /**
     * Tell the person who suggested it, and for a lodge run here, its owners and admins (in the bell
     * only, and without saying who suggested it).
     *
     * @param  list<string>  $fields
     */
    private function tellAboutApplied(LodgeCorrection $correction, Lodge $lodge, array $fields): void
    {
        $name = $lodge->displayName();
        $what = implode(', ', array_map(fn (string $field) => strtolower(LodgeCorrection::FIELDS[$field]), $fields));

        $correction->user->notify(new LodgeClaimNotification(
            ClubNotification::NOTICE,
            'Your fix to '.$name.' is live',
            'Thank you. We have updated: '.$what.'.',
            $name,
            'members.lodges',
            [],
            'See your suggestions',
        ));

        if (! $lodge->club) {
            return;
        }

        $admins = $lodge->club->users()->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->get();

        Notification::send($admins, new LodgeClaimNotification(
            ClubNotification::NOTICE,
            'Directory listing updated: '.$name,
            'We updated your lodge\'s public listing ('.$what.') after someone reported it was out of date. Your own meeting dates here have not changed; update them too if they need the same fix.',
            $name,
            $lodge->status === 'active' ? 'lodges.show' : 'member.dashboard',
            $lodge->status === 'active' ? ['slug' => $lodge->slug] : ['slug' => $lodge->club->slug],
            'See the listing',
            mail: false,
        ));
    }

    /**
     * Change a suggestion's status and the fields that record who decided. These are not
     * mass-assignable on purpose, so nothing but this service can set them.
     *
     * @param  array<string, mixed>|null  $applied
     */
    private function settle(LodgeCorrection $correction, string $status, User $by, ?string $note, ?array $applied = null): void
    {
        $correction->forceFill([
            'status' => $status,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
            'applied' => $applied,
        ])->save();
    }
}
