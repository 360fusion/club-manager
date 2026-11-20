<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\EventStatus;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Models\Lodge;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\NewsTag;
use App\Models\Post;
use App\Services\DuesTracker;
use App\Services\Events\EventPayload;
use App\Services\MemberCalendar;
use App\Support\ClubAccess;
use App\Support\MemberScope;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists across a member's clubs, or within one club: /members/news and
 * /members/{club}/news are the same page at two scopes.
 */
class MemberListController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function scopeProps(MemberScope $scope): array
    {
        return [
            'scopeClub' => $scope->club ? ['name' => $scope->club->name, 'slug' => $scope->club->slug, 'colour' => $scope->club->colourKey()] : null,
            'clubOptions' => $scope->memberClubs->map(fn ($club) => ['name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()])->values()->all(),
        ];
    }

    public function events(Request $request): Response
    {
        $scope = MemberScope::fromRequest($request);
        $now = CarbonImmutable::now();

        $items = app(MemberCalendar::class)
            ->items($scope, $now->startOfDay(), $now->addMonths(6))
            ->where('type', 'event')
            ->values();

        $bookings = $this->bookings($scope)->whereIn('event_id', $items->pluck('id'))->get()->keyBy('event_id');

        $items = $items->map(fn (array $item) => [
            ...$this->serialise($item),
            'club' => [...$item['club'], 'currency_symbol' => $scope->clubFor($item['club']['id'])->currencySymbol()],
            'booking' => $bookings->has($item['id']) ? EventPayload::registration($bookings->get($item['id'])) : null,
        ])->values();

        $past = $this->bookings($scope)
            ->whereHas('event', fn ($query) => $query->whereIn('club_id', $scope->clubIds())->where('status', '!=', EventStatus::Cancelled->value)->whereBetween('starts_at', [$now->subYear(), $now->startOfDay()]))
            ->whereIn('status', EventRegistration::HOLDING_PLACE)
            ->get()
            ->sortByDesc(fn (EventRegistration $registration) => $registration->event->starts_at)
            ->take(20)
            ->map(fn (EventRegistration $registration) => [
                'key' => 'past-'.$registration->id,
                'id' => $registration->event_id,
                'club' => $this->clubPayload($scope->clubFor($registration->event->club_id)),
                'title' => $registration->event->title,
                'start' => $registration->event->starts_at->toIso8601String(),
                'where' => $registration->event->location,
                'booking' => EventPayload::registration($registration),
            ])
            ->values();

        return Inertia::render('Members/AllEvents', [...$this->scopeProps($scope), 'events' => $items, 'past' => $past]);
    }

    /**
     * The member's own bookings that hold or wait for a place, with what the booking card shows.
     *
     * @return Builder<EventRegistration>
     */
    private function bookings(MemberScope $scope): Builder
    {
        return EventRegistration::where('user_id', $scope->user->id)
            ->whereIn('status', [...EventRegistration::HOLDING_PLACE, 'waitlisted'])
            ->with(['event.club', 'paymentMethod', 'attendees.starter', 'attendees.main', 'attendees.dessert']);
    }

    public function meetings(Request $request, ?string $slug = null): Response
    {
        $scope = MemberScope::fromRequest($request, $slug);
        $now = CarbonImmutable::now();

        $items = app(MemberCalendar::class)
            ->items($scope, $now->startOfDay(), $now->addMonths(12))
            ->where('type', 'meeting')
            ->map(fn (array $item) => $this->serialise($item))
            ->values();

        $rsvps = MeetingRsvp::whereIn('meeting_id', $items->pluck('id'))
            ->where('user_id', $scope->user->id)
            ->with('guests')
            ->get()
            ->keyBy('meeting_id');

        $items = $items->map(function (array $item) use ($rsvps) {
            $rsvp = $rsvps->get($item['id']);

            return [...$item, 'rsvp' => $rsvp ? [
                'attendance_status' => $rsvp->attendance_status,
                'dietary_requirements' => $rsvp->dietary_requirements,
                'apology_reason' => $rsvp->apology_reason,
                'payment_status' => $rsvp->payment_status ?? 'unpaid',
                'guests' => $rsvp->guests,
            ] : null];
        });

        $recent = Meeting::whereIn('club_id', $scope->clubIds())
            ->where('status', 'published')
            ->whereDate('meeting_date', '<', today())
            ->orderByDesc('meeting_date')
            ->limit(10)
            ->get()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'club' => $this->clubPayload($scope->clubFor($meeting->club_id)),
                'title' => $meeting->title,
                'date' => $meeting->meeting_date->toDateString(),
                'reply' => MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $scope->user->id)->value('attendance_status'),
            ]);

        return Inertia::render('Members/Meetings', [...$this->scopeProps($scope), 'meetings' => $items, 'recent' => $recent]);
    }

    public function news(Request $request, ?string $slug = null): Response
    {
        $scope = MemberScope::fromRequest($request, $slug);

        $filters = $this->newsFilters($request);

        // Followed lodges that keep their news here appear beside the member's own clubs, on the
        // all-clubs page only. What is shown is still limited by each post's audience, so a
        // follower only ever gets what a member of any club is allowed to read.
        $followedClubs = $scope->isSingle() ? collect() : $this->followedClubs($scope);

        $posts = Post::whereIn('club_id', $scope->clubIds()->merge($followedClubs->keys()))
            ->with(['author', 'media', 'tags:id,name,slug,color'])
            ->published()
            ->visibleTo($scope->user)
            ->filter($filters)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Post $post) use ($scope, $followedClubs) {
                $club = $scope->clubFor($post->club_id) ?? $followedClubs->get($post->club_id);
                $at = $post->published_at ?? $post->created_at;

                return [
                    'id' => $post->id,
                    'following' => $followedClubs->has($post->club_id),
                    'club' => ['name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()],
                    'title' => $post->title,
                    'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($post->excerpt ?: $post->content))), 240),
                    'at' => $at->toIso8601String(),
                    'is_new' => $at->gt(now()->subDays(7)),
                    'author' => $post->author?->name,
                    'cover' => $post->cover_image_url ?: ($post->getFirstMediaUrl('cover') ?: null),
                    'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags((string) $post->content)) / 200)),
                    'attachments' => count($post->attachments ?? []),
                    'visibility' => ['value' => $post->visibility->value, 'label' => $post->visibility->label()],
                    'tags' => $post->tags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug, 'color' => $tag->color])->values(),
                ];
            });

        return Inertia::render('Members/News', [
            ...$this->scopeProps($scope),
            'posts' => $posts,
            'filters' => [...$filters, 'club' => $scope->club?->slug],
            'tagOptions' => NewsTag::browsableFor($scope->clubIds()->all(), $scope->user),
        ]);
    }

    /**
     * The clubs of the managed lodges this member follows and does not belong to, by club id.
     *
     * @return Collection<int, Club>
     */
    private function followedClubs(MemberScope $scope): Collection
    {
        return $scope->user->followedLodges()
            ->where('lodges.status', 'active')
            ->whereNotNull('lodges.club_id')
            ->whereNotIn('lodges.club_id', $scope->clubIds()->all())
            ->with('club')
            ->get()
            ->map(fn (Lodge $lodge) => $lodge->club)
            ->filter()
            ->keyBy('id');
    }

    /**
     * The news filters from the query string. Anything malformed is ignored rather than
     * rejected, so a stale or hand-edited link still shows the list.
     *
     * @return array{q: string, tags: list<string>, from: ?string, to: ?string}
     */
    private function newsFilters(Request $request): array
    {
        $valid = Validator::make($request->query(), [
            'q' => 'nullable|string|max:100',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:80',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ])->valid();

        return [
            'q' => trim((string) ($valid['q'] ?? '')),
            'tags' => array_values(array_unique($valid['tags'] ?? [])),
            'from' => ($valid['from'] ?? null) ? Carbon::parse($valid['from'])->toDateString() : null,
            'to' => ($valid['to'] ?? null) ? Carbon::parse($valid['to'])->toDateString() : null,
        ];
    }

    public function dues(Request $request): Response
    {
        $scope = MemberScope::fromRequest($request);
        $userId = $scope->user->id;

        $tracker = app(DuesTracker::class);
        $bank = [];
        $subscriptions = MemberSubscription::whereIn('club_id', $scope->clubIds())
            ->whereHas('member', fn ($member) => $member->where('user_id', $userId))
            ->with('payments')
            ->orderByDesc('due_date')
            ->get()
            ->map(function (MemberSubscription $subscription) use ($scope, $tracker, &$bank) {
                $club = $scope->clubFor($subscription->club_id);

                return [
                    ...$tracker->describe($subscription, $club),
                    'club' => $this->clubPayload($club),
                    'outstanding' => $subscription->status->isOutstanding(),
                    // Where to send it, once per lodge.
                    'bank' => $bank[$club->id] ??= $tracker->bankDetails($club),
                ];
            });

        $invoices = Invoice::whereIn('club_id', $scope->clubIds())
            ->where('user_id', $userId)
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'club' => $this->clubPayload($scope->clubFor($invoice->club_id)),
                'number' => $invoice->invoice_number,
                'title' => $invoice->title,
                'amount' => number_format((float) $invoice->amount, 2),
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at?->toDateString(),
                'created_at' => $invoice->created_at?->toDateString(),
            ]);

        return Inertia::render('Members/AllDues', [...$this->scopeProps($scope), 'subscriptions' => $subscriptions, 'invoices' => $invoices]);
    }

    /**
     * The member says they have paid (or set up a standing order) by bank transfer. Nothing is marked paid: that waits for
     * the Treasurer to see it in the bank. It only pauses dues reminders for four weeks.
     */
    public function saidPaidDues(Request $request, int $id): RedirectResponse
    {
        $subscription = MemberSubscription::where('id', $id)
            ->whereHas('member', fn ($member) => $member->where('user_id', $request->user()->id))
            ->firstOrFail();
        abort_unless(ClubAccess::isActiveMember($request->user(), $subscription->club), 403);

        if ($subscription->status->isOutstanding()) {
            $subscription->forceFill(['said_paid_at' => now()])->save();
        }

        return back()->with('success', 'Thank you. The Treasurer will mark it paid once it shows in the bank.');
    }

    /**
     * @param  Club  $club
     * @return array{name: string, slug: string, colour: string, currency_symbol: string}
     */
    private function clubPayload($club): array
    {
        return ['name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey(), 'currency_symbol' => $club->currencySymbol()];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function serialise(array $item): array
    {
        return [...$item, 'start' => $item['start']->toIso8601String(), 'end' => $item['end']->toIso8601String()];
    }
}
