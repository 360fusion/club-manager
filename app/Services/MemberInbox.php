<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Support\Currencies;
use App\Support\MemberScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "needs your attention" list: things a member has to do, most urgent first.
 */
class MemberInbox
{
    private const EVENT_DEADLINE_DAYS = 7;

    /**
     * @return list<array<string, mixed>>
     */
    public function items(MemberScope $scope): array
    {
        $items = collect()
            ->concat($this->unansweredSummonses($scope))
            ->concat($this->unpaidDining($scope))
            ->concat($this->outstandingDues($scope))
            ->concat($this->eventDeadlines($scope))
            ->concat($this->pendingApprovals($scope));

        return $items
            ->sortBy([fn (array $a, array $b) => $b['severity'] <=> $a['severity'], fn (array $a, array $b) => strcmp((string) $a['due'], (string) $b['due'])])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function unansweredSummonses(MemberScope $scope): Collection
    {
        $meetings = Meeting::whereIn('club_id', $scope->clubIds())
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', today())
            ->where(fn ($cutoff) => $cutoff->whereNull('rsvp_cutoff_at')->orWhere('rsvp_cutoff_at', '>', now()))
            ->orderBy('meeting_date')
            ->get();

        $answered = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $scope->user->id)
            ->whereNotNull('responded_at')
            ->pluck('meeting_id');

        return $meetings->reject(fn (Meeting $meeting) => $answered->contains($meeting->id))
            ->map(function (Meeting $meeting) use ($scope) {
                $club = $scope->clubFor($meeting->club_id);

                return $this->item('meeting_rsvp', 2, 'Reply to '.$meeting->title, $club, ($meeting->meeting_date?->format('j M Y') ?? '').($meeting->rsvp_cutoff_at ? ' · replies close '.$meeting->rsvp_cutoff_at->format('j M') : ''), $meeting->rsvp_cutoff_at ?? $meeting->meeting_date, [
                    'type' => 'meeting', 'id' => $meeting->id, 'slug' => $club->slug,
                ]);
            })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function unpaidDining(MemberScope $scope): Collection
    {
        $rsvps = MeetingRsvp::where('user_id', $scope->user->id)
            ->where('attendance_status', 'attending_dining')
            ->where(fn ($payment) => $payment->whereNull('payment_status')->orWhere('payment_status', 'unpaid'))
            ->whereHas('meeting', fn ($meeting) => $meeting
                ->whereIn('club_id', $scope->clubIds())
                ->where('status', 'published')
                ->where('dining_cost_member', '>', 0)
                ->whereDate('meeting_date', '>=', today()))
            ->with('meeting')
            ->get();

        return $rsvps->map(function (MeetingRsvp $rsvp) use ($scope) {
            $meeting = $rsvp->meeting;
            $club = $scope->clubFor($meeting->club_id);

            return $this->item('dining_payment', 2, 'Pay for dining at '.$meeting->title, $club, Currencies::format((float) $meeting->dining_cost_member, $club).' · '.($meeting->meeting_date?->format('j M Y') ?? ''), $meeting->meeting_date, null, route('member.meetings.summons', ['slug' => $club->slug, 'id' => $meeting->id], false));
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function outstandingDues(MemberScope $scope): Collection
    {
        return MemberSubscription::whereIn('club_id', $scope->clubIds())
            ->whereHas('member', fn ($member) => $member->where('user_id', $scope->user->id))
            ->whereIn('status', array_map(fn (SubscriptionStatus $s) => $s->value, array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $s) => $s->isOutstanding())))
            ->get()
            ->map(function (MemberSubscription $subscription) use ($scope) {
                $club = $scope->clubFor($subscription->club_id);
                $arrears = $subscription->status->isArrears();

                return $this->item('dues', $arrears ? 3 : 2, ($arrears ? 'Overdue dues' : 'Dues to pay').' at '.$club->name, $club, Currencies::format($subscription->balance_due, $club).($subscription->due_date ? ' · due '.$subscription->due_date->format('j M Y') : ''), $subscription->due_date, null, route('members.dues', ['club' => $club->slug], false));
            })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function eventDeadlines(MemberScope $scope): Collection
    {
        $events = Event::whereIn('club_id', $scope->clubIds())
            ->visibleTo($scope->user)
            ->where('status', 'upcoming')
            ->whereBetween('rsvp_deadline', [now(), now()->addDays(self::EVENT_DEADLINE_DAYS)])
            ->get();

        $answered = EventRegistration::whereIn('event_id', $events->pluck('id'))
            ->where('user_id', $scope->user->id)
            ->pluck('event_id');

        return $events->reject(fn (Event $event) => $answered->contains($event->id))
            ->map(function (Event $event) use ($scope) {
                $club = $scope->clubFor($event->club_id);

                return $this->item('event_rsvp', 1, 'Reply to '.$event->title, $club, 'replies close '.$event->rsvp_deadline->format('j M'), $event->rsvp_deadline, $event->allowsQuickReply() ? [
                    'type' => 'event', 'id' => $event->id, 'slug' => $club->slug,
                ] : null, route('member.events', ['slug' => $club->slug], false));
            })->values();
    }

    /**
     * Club staff see how many new members are waiting to be approved.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingApprovals(MemberScope $scope): Collection
    {
        return $scope->clubs()
            ->filter(fn ($club) => $scope->isStaffIn($club->id))
            ->map(function ($club) {
                $waiting = DB::table('club_user')->where('club_id', $club->id)->where('status', 'pending')->count();

                return $waiting === 0 ? null : $this->item('approvals', 1, $waiting.' '.($waiting === 1 ? 'member is' : 'members are').' waiting for approval', $club, 'Club admin', null, null, route('admin.club_acc.members.index', ['clubSlug' => $club->slug], false));
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array{type: string, id: int, slug: string}|null  $reply  set when one tap can answer it
     * @return array<string, mixed>
     */
    private function item(string $kind, int $severity, string $title, $club, string $detail, $due, ?array $reply = null, ?string $url = null): array
    {
        return [
            'kind' => $kind,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'club' => ['name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()],
            'due' => $due?->toIso8601String(),
            'reply' => $reply,
            'url' => $url ?? route('member.dashboard', ['slug' => $club->slug], false),
        ];
    }
}
