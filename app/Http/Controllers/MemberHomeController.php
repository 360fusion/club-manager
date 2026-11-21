<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Enums\EventStatus;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingDiningPayment;
use App\Services\Meetings\MeetingReplies;
use App\Services\MemberFeed;
use App\Services\MemberInbox;
use App\Support\Currencies;
use App\Support\MemberScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in member's home: an overview of every club they belong to,
 * what needs their attention, what is coming up and the latest club news.
 */
class MemberHomeController extends Controller
{
    private const UP_NEXT_LIMIT = 6;

    public function __construct(private readonly MemberInbox $inbox) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $scope = MemberScope::for($user);
        $clubs = $scope->memberClubs;
        $pending = $user->clubs()->with('clubType')->wherePivot('status', ClubMemberStatus::Pending->value)->get();

        return Inertia::render('Members/Dashboard', [
            'clubs' => $clubs->map(fn (Club $club) => $this->clubSummary($club))->all(),
            'pendingClubs' => $pending->map(fn (Club $club) => $this->clubSummary($club))->all(),
            'inbox' => $this->inbox->items($scope),
            'upNext' => $this->upNext($user, $clubs),
            'feed' => Inertia::defer(fn () => app(MemberFeed::class)->items($user, $clubs)),
        ]);
    }

    /**
     * @return array{id: int, name: string, slug: string, type_name: string|null, role: string, member_number: string, is_staff: bool}
     */
    private function clubSummary(Club $club): array
    {
        $role = $club->pivot->role ?? 'member';

        return [
            'id' => $club->id,
            'name' => $club->name,
            'slug' => $club->slug,
            'colour' => $club->colourKey(),
            'type_name' => $club->clubType?->name,
            'role' => $role,
            'member_number' => $club->pivot->member_number ?? '',
            'is_staff' => ! in_array($role, ['member', 'visitor'], true),
        ];
    }

    /**
     * The next meetings and events across the member's clubs, soonest first, with their reply, guests and what they still owe.
     *
     * @param  Collection<int, Club>  $clubs
     * @return list<array<string, mixed>>
     */
    private function upNext(User $user, Collection $clubs): array
    {
        if ($clubs->isEmpty()) {
            return [];
        }

        $clubIds = $clubs->pluck('id');

        $meetings = Meeting::with('club')->whereIn('club_id', $clubIds)
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', today())
            ->orderBy('meeting_date')
            ->limit(self::UP_NEXT_LIMIT)
            ->get();

        $meetingReplies = MeetingRsvp::with('guests')
            ->whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('meeting_id');

        $events = Event::with('club')->whereIn('club_id', $clubIds)
            ->visibleTo($user)
            ->where('status', EventStatus::Upcoming->value)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(self::UP_NEXT_LIMIT)
            ->get();

        $eventReplies = EventRegistration::withCount('attendees')->with('paymentMethod')
            ->whereIn('event_id', $events->pluck('id'))
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_id');

        $owes = fn (float $amount, int $clubId): ?string => $amount > 0 ? Currencies::format($amount, $clubs->firstWhere('id', $clubId)) : null;

        $diningPayment = app(MeetingDiningPayment::class);
        $takesOnline = [];

        return $meetings->map(function (Meeting $meeting) use ($clubs, $meetingReplies, $owes, $diningPayment, &$takesOnline) {
            $club = $clubs->firstWhere('id', $meeting->club_id);
            $rsvp = $meetingReplies[$meeting->id] ?? null;

            return [
                'type' => 'meeting',
                'id' => $meeting->id,
                'club_slug' => $club->slug,
                'club_name' => $club->name,
                'club_colour' => $club->colourKey(),
                'title' => $meeting->title,
                'at' => ($meeting->starts_at ? $meeting->meeting_date->copy()->setTimeFromTimeString((string) $meeting->starts_at) : $meeting->meeting_date)->toIso8601String(),
                'where' => $meeting->venue,
                'reply' => $rsvp?->attendance_status,
                'guests' => $rsvp ? MeetingReplies::listedGuests($rsvp)->count() : 0,
                'owes' => $rsvp ? $owes(MeetingReplies::owed($meeting, $rsvp)['amount'], $meeting->club_id) : null,
                // They told the lodge they paid by bank transfer: not chased while the Treasurer waits for the statement.
                'said_paid' => $rsvp?->said_paid_at !== null,
                // No bank details, card or PayPal: dining is paid on the night, so there is nothing to pay now.
                'pay_ahead' => MeetingDiningPayment::takesBank($meeting) || ($takesOnline[$club->id] ??= $diningPayment->takesOnline($club)),
                'closed' => $meeting->rsvpClosed(),
                'url' => route('member.meetings.summons', ['slug' => $club->slug, 'id' => $meeting->id], false),
            ];
        })->concat($events->map(function (Event $event) use ($clubs, $eventReplies, $owes) {
            $club = $clubs->firstWhere('id', $event->club_id);
            $booking = $eventReplies[$event->id] ?? null;
            $settled = ! $booking || $booking->status !== 'attending' || in_array($booking->payment_status, ['waived', 'refunded'], true);

            return [
                'type' => 'event',
                'id' => $event->id,
                'club_slug' => $club->slug,
                'club_name' => $club->name,
                'club_colour' => $club->colourKey(),
                'title' => $event->title,
                'at' => $event->starts_at->toIso8601String(),
                'where' => $event->location,
                'reply' => $booking?->status,
                'guests' => $booking ? max(0, $booking->attendees_count - 1) : 0,
                'owes' => $settled ? null : $owes($booking->balanceDue(), $event->club_id),
                'said_paid' => $booking?->said_paid_at !== null,
                'pay_ahead' => $booking?->paymentMethod?->type !== ClubPaymentMethod::DOOR,
                'closed' => (bool) $event->is_booking_closed,
                'url' => route('member.events', ['slug' => $club->slug], false),
            ];
        }))
            ->sortBy('at')
            ->take(self::UP_NEXT_LIMIT)
            ->values()
            ->all();
    }
}
