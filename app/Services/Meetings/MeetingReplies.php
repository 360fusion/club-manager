<?php

namespace App\Services\Meetings;

use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Mail\MeetingNumbersMail;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A meeting's replies: the lodge's reply schedule (ask, dining deadline, confirm), the dining numbers for the Secretary,
 * what each person owes for dining, and the rule that once the numbers have gone to the caterer nobody can add dining
 * without the Secretary (dropping it, or the meeting, is always allowed).
 */
class MeetingReplies
{
    public const ATTENDING = ['attending_dining', 'attending_meeting_only'];

    public const ANSWERS = ['attending_dining', 'attending_meeting_only', 'apologies'];

    /**
     * How many days before a meeting each step happens, from the lodge's settings: ask everyone who has not replied,
     * send the Secretary the dining numbers (replies for dining close at midnight before), remind those coming.
     *
     * @return array{ask: int, deadline: int, confirm: int}
     */
    public static function days(Club $club): array
    {
        $settings = $club->settings ?? [];

        return [
            'ask' => (int) ($settings['meeting_ask_days'] ?? 7),
            'deadline' => (int) ($settings['meeting_deadline_days'] ?? 3),
            'confirm' => (int) ($settings['meeting_confirm_days'] ?? 1),
        ];
    }

    /**
     * A new meeting's dining deadline: midnight at the start of the numbers day, as the lodge's wall clock.
     */
    public static function defaultCutoff(Club $club, CarbonInterface|string $meetingDate): Carbon
    {
        return Carbon::parse($meetingDate)->subDays(self::days($club)['deadline'] + 1)->endOfDay();
    }

    public static function paymentReference(Meeting $meeting, User $user): string
    {
        return ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.strtoupper((string) last(explode(' ', $user->name)));
    }

    /**
     * The places someone's reply takes at the festive board: themselves if dining, and their dining guests if they are coming.
     *
     * @param  iterable<mixed>  $guests  guest models or submitted guest arrays
     */
    public static function diningHeads(?string $status, iterable $guests): int
    {
        if (! in_array($status, self::ATTENDING, true)) {
            return 0;
        }

        return ($status === 'attending_dining' ? 1 : 0) + collect($guests)->filter(fn ($guest) => (bool) data_get($guest, 'attending_dining'))->count();
    }

    /**
     * @return array{status: ?string, heads: int, paid: bool}
     */
    public static function before(?MeetingRsvp $rsvp): array
    {
        return [
            'status' => $rsvp?->attendance_status,
            'heads' => $rsvp ? self::diningHeads($rsvp->attendance_status, $rsvp->guests) : 0,
            'paid' => $rsvp && ($rsvp->payment_status === 'paid' || $rsvp->guests->contains('payment_status', 'paid')),
        ];
    }

    /**
     * After the dining deadline a reply may drop dining or guests but not add them: the caterer has the numbers.
     *
     * @param  array{status: ?string, heads: int, paid: bool}  $before
     * @param  iterable<mixed>  $guests
     *
     * @throws ValidationException
     */
    public static function guardDining(Meeting $meeting, array $before, string $status, iterable $guests): void
    {
        if ($meeting->rsvpClosed() && self::diningHeads($status, $guests) > $before['heads']) {
            throw ValidationException::withMessages([
                'cutoff' => 'The dining numbers have gone to the caterer, so dining can no longer be added here. You can still come to the meeting only or send apologies; to dine, please ask the Secretary.',
            ]);
        }
    }

    /**
     * Once the Secretary has the numbers, any change to someone's reply is passed on with the new totals.
     *
     * @param  array{status: ?string, heads: int, paid: bool}  $before
     */
    public function afterChange(Meeting $meeting, MeetingRsvp $rsvp, array $before): void
    {
        if (! $meeting->numbers_sent_at) {
            return;
        }

        $rsvp->load(['guests', 'user']);
        $heads = self::diningHeads($rsvp->attendance_status, $rsvp->guests);

        if ($before['status'] === $rsvp->attendance_status && $before['heads'] === $heads) {
            return;
        }

        $change = ($rsvp->user->name ?? 'A member').': '.self::label($before['status']).($before['heads'] ? " ({$before['heads']} dining)" : '')
            .' → '.self::label($rsvp->attendance_status).($heads ? " ({$heads} dining)" : '').'.';

        if ($heads < $before['heads'] && $before['paid']) {
            $change .= ' They had already paid for dining: refund them from the dining list if needed.';
        }

        $this->sendNumbers($meeting, $change, $this->numbers($meeting)['dining'] - $heads + $before['heads']);
    }

    /**
     * Email the Secretary the numbers, or an update with what changed. Returns false when the lodge has nobody to send them to.
     */
    public function sendNumbers(Meeting $meeting, ?string $change = null, ?int $diningBefore = null): bool
    {
        $club = $meeting->club;
        $email = (Member::summonsSecretary($club)['email'] ?? null) ?: $club->email;

        if (! $email) {
            return false;
        }

        $numbers = $this->numbers($meeting);
        Mail::to($email)->queue(new MeetingNumbersMail($club, $meeting, $numbers, $change));

        // The caterer only hears when the dining count itself changes, without names.
        $caterer = $club->settings['caterer_email'] ?? null;
        if ($caterer && ($change === null || $numbers['dining'] !== $diningBefore)) {
            Mail::to($caterer)->queue(new MeetingNumbersMail($club, $meeting, $numbers, $change === null ? null : "The numbers have changed: now {$numbers['dining']} dining.", true));
        }

        return true;
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            'attending_dining' => 'attending and dining',
            'attending_meeting_only' => 'attending, not dining',
            'apologies' => 'apologies',
            default => 'no reply',
        };
    }

    /**
     * Replace a reply's guests with those submitted. A guest already paid for (or refunded) keeps their row and so their
     * payment: matched by name while they stay on the reply, and kept as not dining when taken off, so the Secretary
     * sees them under "paid but no longer dining" and can refund them.
     *
     * @param  array<int, array<string, mixed>>  $guests
     */
    public static function syncGuests(Meeting $meeting, MeetingRsvp $rsvp, array $guests): void
    {
        $guests = array_values(array_filter($guests, fn (array $guest) => filled($guest['guest_name'] ?? null)));
        $names = collect($guests)->pluck('guest_name');
        $settled = $rsvp->guests()->whereIn('payment_status', ['paid', 'refunded'])->get();
        $paid = $settled->whereIn('guest_name', $names->all())->keyBy('guest_name');
        $rsvp->guests()->whereNotIn('id', $settled->pluck('id'))->delete();
        $rsvp->guests()->whereIn('id', $settled->whereNotIn('guest_name', $names->all())->pluck('id'))->update(['attending_dining' => false]);

        foreach ($guests as $guest) {
            $dining = (bool) ($guest['attending_dining'] ?? true);
            $attributes = [
                'guest_title_rank' => $guest['guest_title_rank'] ?? null,
                'home_club_lodge' => $guest['home_club_lodge'] ?? null,
                'attending_dining' => $dining,
                'dietary_requirements' => $guest['dietary_requirements'] ?? null,
            ];

            if ($existing = $paid->pull($guest['guest_name'])) {
                $existing->update($attributes);

                continue;
            }

            $rsvp->guests()->create($attributes + ['guest_name' => $guest['guest_name'], 'dining_fee' => $dining ? $meeting->dining_cost_guest : 0]);
        }

        $rsvp->unsetRelation('guests');
    }

    /**
     * The guests a member sees and edits on their reply: not one they took off after paying, which only the Secretary
     * deals with now.
     *
     * @return Collection<int, MeetingRsvpGuest>
     */
    public static function listedGuests(MeetingRsvp $rsvp): Collection
    {
        return $rsvp->guests->reject(fn ($guest) => ! $guest->attending_dining && in_array($guest->payment_status, ['paid', 'refunded'], true))->values();
    }

    /**
     * Names brethren with their rank, as on the summons and in the minutes ("WBro John Smith"), from the lodge's member
     * records; an account with no member record keeps its own name.
     *
     * @param  Collection<int, mixed>  $userIds
     * @return \Closure(?User): string
     */
    public static function rankedNamer(int $clubId, Collection $userIds): \Closure
    {
        $members = Member::where('club_id', $clubId)->whereIn('user_id', $userIds->filter()->unique()->values())->get()->keyBy('user_id');

        return fn (?User $user) => match (true) {
            $user === null => 'Member',
            $members->has($user->id) => $members[$user->id]->formatted_rank_name,
            default => $user->name,
        };
    }

    /**
     * Members of the lodge (not visitors) who have not answered yet.
     *
     * @return Collection<int, User>
     */
    public function nonRepliers(Meeting $meeting): Collection
    {
        $answered = MeetingRsvp::where('meeting_id', $meeting->id)->whereIn('attendance_status', self::ANSWERS)->pluck('user_id');

        return $meeting->club->users()
            ->wherePivot('status', ClubMemberStatus::Active->value)
            ->wherePivot('role', '!=', ClubRole::Visitor->value)
            ->whereNotIn('users.id', $answered)
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, MeetingRsvp>
     */
    public function attending(Meeting $meeting): Collection
    {
        return MeetingRsvp::where('meeting_id', $meeting->id)
            ->whereIn('attendance_status', self::ATTENDING)
            ->with(['user', 'guests'])
            ->get()
            ->filter(fn (MeetingRsvp $rsvp) => $rsvp->user !== null)
            ->values();
    }

    /**
     * The reply row a private link points at, made when someone has none yet (they joined after the summons went out).
     */
    public function rsvpFor(Meeting $meeting, User $user): MeetingRsvp
    {
        $rsvp = MeetingRsvp::firstOrNew(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        if (! $rsvp->exists) {
            $rsvp->forceFill([
                'token_hash' => Str::random(40),
                'token_expires_at' => $this->linkExpiry($meeting),
                'payment_reference' => self::paymentReference($meeting, $user),
            ])->save();
        }

        return $rsvp;
    }

    /**
     * A private link to someone's reply page, optionally with an answer already picked. It works until the end of the
     * meeting day and needs no sign-in, like the summons link (which stays valid: no new token is made for it).
     */
    public function replyUrl(Meeting $meeting, MeetingRsvp $rsvp, ?string $answer = null): string
    {
        return URL::temporarySignedRoute('summons.reply', $this->linkExpiry($meeting), ['rsvp' => $rsvp->id] + ($answer ? ['answer' => $answer] : []));
    }

    public function linkExpiry(Meeting $meeting): CarbonInterface
    {
        return $meeting->club->atLocalTime($meeting->meeting_date->copy()->endOfDay());
    }

    /**
     * What someone still owes for dining: their own place if dining and unpaid, and each unpaid dining guest.
     *
     * @return array{amount: float, member: bool, guest_ids: list<int>}
     */
    public static function owed(Meeting $meeting, MeetingRsvp $rsvp): array
    {
        $unpaid = fn (?string $status) => ! in_array($status, ['paid', 'waived'], true);
        $member = $rsvp->attendance_status === 'attending_dining' && $unpaid($rsvp->payment_status);
        $guests = in_array($rsvp->attendance_status, self::ATTENDING, true)
            ? $rsvp->guests->filter(fn ($guest) => $guest->attending_dining && $unpaid($guest->payment_status))
            : collect();

        $amount = ($member ? (float) $meeting->dining_cost_member : 0.0)
            + $guests->sum(fn ($guest) => (float) ($guest->dining_fee ?: $meeting->dining_cost_guest));

        return ['amount' => round($amount, 2), 'member' => $member, 'guest_ids' => $guests->pluck('id')->values()->all()];
    }

    /**
     * The numbers the Secretary and caterer need. Visitors who reply count towards the dining and attendance totals;
     * only the lodge's own members are chased for a reply.
     *
     * @return array{dining: int, dining_members: int, dining_guests: int, meeting_only: int, apologies: list<array{name: string, reason: ?string}>, no_reply: list<string>, dietary: list<array{person: string, requirement: string}>, unpaid_heads: int, outstanding: float}
     */
    public function numbers(Meeting $meeting): array
    {
        $rsvps = MeetingRsvp::where('meeting_id', $meeting->id)->whereIn('attendance_status', self::ANSWERS)->with(['user', 'guests'])->get();
        $attending = $rsvps->whereIn('attendance_status', self::ATTENDING);
        $nonRepliers = $this->nonRepliers($meeting);

        $name = self::rankedNamer($meeting->club_id, $rsvps->pluck('user_id')->merge($nonRepliers->pluck('id')));

        $diningMembers = $attending->where('attendance_status', 'attending_dining')->count();
        $diningGuests = $attending->sum(fn (MeetingRsvp $rsvp) => $rsvp->guests->where('attending_dining', true)->count());

        $dietary = [];
        foreach ($attending as $rsvp) {
            if ($rsvp->attendance_status === 'attending_dining' && filled($rsvp->dietary_requirements)) {
                $dietary[] = ['person' => $name($rsvp->user), 'requirement' => (string) $rsvp->dietary_requirements];
            }
            foreach ($rsvp->guests->where('attending_dining', true) as $guest) {
                if (filled($guest->dietary_requirements)) {
                    $dietary[] = ['person' => $guest->guest_name.' (guest of '.$name($rsvp->user).')', 'requirement' => (string) $guest->dietary_requirements];
                }
            }
        }

        $owing = $attending->map(fn (MeetingRsvp $rsvp) => self::owed($meeting, $rsvp));

        return [
            'dining' => $diningMembers + $diningGuests,
            'dining_members' => $diningMembers,
            'dining_guests' => $diningGuests,
            'meeting_only' => $attending->where('attendance_status', 'attending_meeting_only')->count(),
            'apologies' => $rsvps->where('attendance_status', 'apologies')
                ->map(fn (MeetingRsvp $rsvp) => ['name' => $name($rsvp->user), 'reason' => $rsvp->apology_reason])
                ->sortBy('name')->values()->all(),
            'no_reply' => $nonRepliers->map($name)->sort()->values()->all(),
            'dietary' => $dietary,
            'unpaid_heads' => $owing->sum(fn (array $owed) => ($owed['member'] ? 1 : 0) + count($owed['guest_ids'])),
            'outstanding' => round($owing->sum('amount'), 2),
        ];
    }
}
