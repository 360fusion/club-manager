<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\ClubVisitorAccess;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Post;
use App\Models\Province;
use App\Models\User;
use App\Support\ClubAccess;
use App\Support\VisitorSummons;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public directory: find a lodge or chapter, see where and when it meets, and visit.
 */
class LodgeDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:60',
            'order' => 'nullable|string|max:60',
            'day' => 'nullable|in:'.implode(',', LodgeSchedule::DAYS),
            'when' => 'nullable|in:week,month',
        ]);

        $query = Lodge::query()->listed()->with(self::relations());

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $like = '%'.$q.'%';
            $query->where(fn ($match) => $match
                ->whereLike('name', $like)
                ->orWhere('number', $q)
                ->orWhereHas('masonicHall', fn ($hall) => $hall
                    ->whereLike('name', $like)
                    ->orWhereLike('town', $like)
                    ->orWhereLike('postcode', $like)));
        }

        if (! empty($filters['province'])) {
            $query->whereHas('province', fn ($province) => $province->where('code', $filters['province']));
        }

        if (! empty($filters['order'])) {
            $query->whereHas('clubType', fn ($type) => $type->where('code', $filters['order']));
        }

        if (! empty($filters['day'])) {
            $query->whereHas('schedules', fn ($schedule) => $schedule->where('day_of_week', $filters['day']));
        }

        if (! empty($filters['when'])) {
            $query->whereIn('id', $this->meetingWithin($filters['when'] === 'week' ? 7 : 31, clone $query));
        }

        $following = $this->followedIds($request);

        $lodges = $query->orderBy('name')->paginate(24)->withQueryString()
            ->through(fn (Lodge $lodge) => [...$this->card($lodge), 'following' => in_array($lodge->id, $following, true)]);

        return Inertia::render('Lodges/Index', [
            'lodges' => $lodges,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'province' => $filters['province'] ?? '',
                'order' => $filters['order'] ?? '',
                'day' => $filters['day'] ?? '',
                'when' => $filters['when'] ?? '',
            ],
            'provinces' => Province::whereHas('lodges', fn ($lodge) => $lodge->listed())
                ->withCount(['lodges' => fn ($lodge) => $lodge->listed()])
                ->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Province $province) => ['code' => $province->code, 'name' => $province->name, 'count' => $province->lodges_count]),
            'orders' => ClubType::whereHas('lodges', fn ($lodge) => $lodge->listed())
                ->withCount(['lodges' => fn ($lodge) => $lodge->listed()])
                ->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (ClubType $type) => ['code' => $type->code, 'name' => $type->name, 'count' => $type->lodges_count]),
            'days' => LodgeSchedule::DAYS,
            'total' => Lodge::listed()->count(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $lodge = Lodge::query()->listed()->with(self::relations())->where('slug', $slug)->firstOrFail();
        $hall = $lodge->masonicHall;

        $sharing = $hall
            ? Lodge::listed()->with(['clubType:id,code,name', 'schedules'])->where('masonic_hall_id', $hall->id)
                ->where('id', '!=', $lodge->id)->orderBy('name')->limit(40)->get()
                ->map(fn (Lodge $other) => $this->card($other, withHall: false))
            : collect();

        $follow = $request->user()?->followedLodges()->whereKey($lodge->id)->first()?->pivot;
        $club = $lodge->club;
        $viewer = $request->user();

        // A managed lodge can share its real meetings. Outside viewers only ever get the short list
        // in VisitorSummons, and only when the lodge has chosen to share them.
        $confirmed = $club ? VisitorSummons::upcoming($club, $viewer) : [];
        $confirmedDates = array_column($confirmed, 'date');
        $expected = array_values(array_filter($this->upcoming($lodge, 8), fn (array $meeting) => ! in_array($meeting['date'], $confirmedDates, true)));

        return Inertia::render('Lodges/Show', [
            'lodge' => [
                ...$this->card($lodge),
                'following' => $follow !== null,
                'in_calendar' => (bool) ($follow?->in_calendar),
                'notify_summons' => (bool) ($follow?->notify_summons),
                'ics_url' => route('lodges.ics', $lodge->slug),
                'meets_text' => $lodge->meets_text,
                'installation_month' => $lodge->installationMonthName(),
                'website_url' => $lodge->website_url,
                'description' => $lodge->description,
                'is_managed' => $lodge->isManaged(),
                'can_claim' => ! $lodge->isManaged(),
                'claim' => $request->user() ? $this->claimFor($lodge, $request->user()->id) : null,
                'last_verified_at' => $lodge->last_verified_at?->toDateString(),
                'source_url' => $lodge->source_url,
                'upcoming' => $expected,
                'confirmed' => $confirmed,
                'visitor' => $club ? $this->visitorState($club, $viewer) : null,
                'club_slug' => $club?->slug,
                'feed_url' => $club ? route('lodges.feed', $lodge->slug) : null,
                'news' => $club ? $this->recentNews($club, $viewer) : [],
                'bulletins' => $club ? $this->bulletins($club) : [],
                'schedules' => $lodge->schedules->map(fn (LodgeSchedule $schedule) => [
                    'occurrence' => $schedule->occurrence,
                    'day_of_week' => $schedule->day_of_week,
                    'months' => $schedule->months,
                    'start_time' => $schedule->start_time,
                ])->values(),
            ],
            'hall' => $hall ? $this->hallPayload($hall) : null,
            'sharing' => $sharing->values(),
        ]);
    }

    public function hall(string $slug): Response
    {
        $hall = MasonicHall::with('province:id,code,name')->where('slug', $slug)->firstOrFail();

        $lodges = Lodge::listed()->with(['clubType:id,code,name', 'schedules'])
            ->where('masonic_hall_id', $hall->id)->orderBy('name')->get()
            ->map(fn (Lodge $lodge) => $this->card($lodge, withHall: false));

        return Inertia::render('Lodges/Hall', [
            'hall' => $this->hallPayload($hall),
            'lodges' => $lodges->values(),
        ]);
    }

    /**
     * The lodge's latest posts that this person is allowed to read.
     *
     * @return list<array<string, mixed>>
     */
    private function recentNews(Club $club, ?User $viewer): array
    {
        return Post::where('club_id', $club->id)->published()->visibleTo($viewer)
            ->orderByRaw('COALESCE(posts.published_at, posts.created_at) DESC')
            ->limit(3)->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->excerpt ?: $post->content)))), 160),
                'date' => ($post->published_at ?? $post->created_at)->toDateString(),
                'url' => route('member.posts.show', ['slug' => $club->slug, 'id' => $post->id], false),
            ])->all();
    }

    /**
     * The public bulletins a lodge lets anyone subscribe to.
     *
     * @return list<array<string, mixed>>
     */
    private function bulletins(Club $club): array
    {
        return $club->newsletterTypes()->where('is_external_subscribable', true)->orderBy('name')->get()
            ->map(fn ($type) => ['id' => $type->id, 'name' => $type->name, 'description' => $type->description, 'needs_approval' => (bool) $type->require_approval])
            ->all();
    }

    /**
     * What this lodge shares with the person looking, and what they can do about it.
     *
     * @return array<string, mixed>
     */
    private function visitorState(Club $club, ?User $viewer): array
    {
        $visibility = VisitorSummons::visibility($club);
        $isMember = $viewer && ClubAccess::isActiveMember($viewer, $club);
        $status = $viewer ? ClubVisitorAccess::where('club_id', $club->id)->where('user_id', $viewer->id)->value('status') : null;

        return [
            'visibility' => $visibility,
            'label' => VisitorSummons::VISIBILITIES[$visibility],
            'is_member' => (bool) $isMember,
            'can_see' => VisitorSummons::canSee($club, $viewer),
            'request_status' => $status,
            'can_request' => $visibility === VisitorSummons::APPROVED_VISITORS && $viewer !== null && ! $isMember && ! in_array($status, [ClubVisitorAccess::PENDING, ClubVisitorAccess::APPROVED], true),
        ];
    }

    /**
     * The signed-in person's latest claim for this lodge, so the page can say where it stands.
     *
     * @return array{status: string}|null
     */
    private function claimFor(Lodge $lodge, int $userId): ?array
    {
        $claim = $lodge->claims()->where('user_id', $userId)->latest('id')->first();

        return $claim ? ['status' => $claim->status] : null;
    }

    /**
     * @return list<int>
     */
    private function followedIds(Request $request): array
    {
        return $request->user()?->followedLodges()->pluck('lodges.id')->all() ?? [];
    }

    /**
     * @return list<string>
     */
    private static function relations(): array
    {
        return ['clubType:id,code,name', 'province:id,code,name', 'masonicHall.province:id,name', 'schedules', 'club'];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Lodge $lodge, bool $withHall = true): array
    {
        $hall = $lodge->masonicHall;

        return [
            'slug' => $lodge->slug,
            'name' => $lodge->displayName(),
            'number' => $lodge->number,
            'order' => $lodge->clubType?->name,
            'order_code' => $lodge->clubType?->code,
            'province' => $lodge->province?->name,
            'meets_text' => $lodge->meets_text,
            'next' => $this->upcoming($lodge, 2),
            'has_pattern' => $lodge->schedules->isNotEmpty(),
            'is_managed' => $lodge->isManaged(),
            'hall' => $withHall && $hall ? [
                'slug' => $hall->slug,
                'name' => $hall->name,
                'town' => $hall->town,
                'postcode' => $hall->postcode,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hallPayload(MasonicHall $hall): array
    {
        $address = $hall->fullAddress();

        return [
            'slug' => $hall->slug,
            'name' => $hall->name,
            'kind' => MasonicHall::KINDS[$hall->kind] ?? $hall->kind,
            'address' => $address,
            'province' => $hall->province?->name,
            'website_url' => $hall->website_url,
            'map_url' => $address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($hall->name.', '.$address) : null,
        ];
    }

    /**
     * The next expected meeting dates across a lodge's patterns, soonest first. The one in the
     * lodge's installation month is marked, because a visitor may want to go to it or avoid it.
     *
     * @return list<array{date: string, time: ?string, installation: bool}>
     */
    private function upcoming(Lodge $lodge, int $limit): array
    {
        $today = CarbonImmutable::today();

        return $lodge->schedules
            ->flatMap(fn (LodgeSchedule $schedule) => array_map(
                fn (CarbonImmutable $date) => ['date' => $date->toDateString(), 'time' => $schedule->start_time, 'installation' => $lodge->isInstallationOn($date)],
                $schedule->nextDates($today, $limit),
            ))
            ->sortBy('date')->take($limit)->values()->all();
    }

    /**
     * Ids of the lodges in the query that are expected to meet in the next $days days.
     *
     * @return list<int>
     */
    private function meetingWithin(int $days, $query): array
    {
        $from = CarbonImmutable::today();
        $to = $from->addDays($days);

        return $query->reorder()->with('schedules')->get()
            ->filter(fn (Lodge $lodge) => $lodge->schedules->contains(fn (LodgeSchedule $schedule) => $schedule->datesBetween($from, $to) !== []))
            ->pluck('id')->all();
    }
}
