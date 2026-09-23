<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\NewsTag;
use App\Models\Post;
use App\Services\MemberCalendar;
use App\Support\MemberScope;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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
            ->map(fn (array $item) => $this->serialise($item))
            ->values();

        return Inertia::render('Members/AllEvents', [...$this->scopeProps($scope), 'events' => $items]);
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
                'date' => $meeting->meeting_date?->toDateString(),
                'reply' => MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $scope->user->id)->value('attendance_status'),
            ]);

        return Inertia::render('Members/Meetings', [...$this->scopeProps($scope), 'meetings' => $items, 'recent' => $recent]);
    }

    public function news(Request $request, ?string $slug = null): Response
    {
        $scope = MemberScope::fromRequest($request, $slug);

        $filters = $this->newsFilters($request);

        $posts = Post::whereIn('club_id', $scope->clubIds())
            ->with(['author', 'media', 'tags:id,name,slug,color'])
            ->published()
            ->visibleTo($scope->user)
            ->filter($filters)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Post $post) use ($scope) {
                $club = $scope->clubFor($post->club_id);
                $at = $post->published_at ?? $post->created_at;

                return [
                    'id' => $post->id,
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

        $subscriptions = MemberSubscription::whereIn('club_id', $scope->clubIds())
            ->whereHas('member', fn ($member) => $member->where('user_id', $userId))
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (MemberSubscription $subscription) => [
                'id' => $subscription->id,
                'club' => $this->clubPayload($scope->clubFor($subscription->club_id)),
                'year' => $subscription->billing_year,
                'due_date' => $subscription->due_date?->toDateString(),
                'amount_due' => number_format((float) $subscription->amount_due, 2),
                'amount_paid' => number_format((float) $subscription->amount_paid, 2),
                'balance' => number_format($subscription->balance_due, 2),
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'outstanding' => $subscription->status->isOutstanding(),
            ]);

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
     * @return array{name: string, slug: string, colour: string}
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
