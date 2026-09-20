<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Post;
use App\Services\MemberCalendar;
use App\Support\MemberScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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
            'scopeClub' => $scope->club ? ['name' => $scope->club->name, 'slug' => $scope->club->slug] : null,
            'clubOptions' => $scope->memberClubs->map(fn ($club) => ['name' => $club->name, 'slug' => $club->slug])->values()->all(),
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

        $recent = Meeting::whereIn('club_id', $scope->clubIds())
            ->where('status', 'published')
            ->whereDate('meeting_date', '<', today())
            ->orderByDesc('meeting_date')
            ->limit(10)
            ->get()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'club' => ['name' => $scope->clubFor($meeting->club_id)->name, 'slug' => $scope->clubFor($meeting->club_id)->slug],
                'title' => $meeting->title,
                'date' => $meeting->meeting_date?->toDateString(),
                'reply' => MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $scope->user->id)->value('attendance_status'),
            ]);

        return Inertia::render('Members/Meetings', [...$this->scopeProps($scope), 'meetings' => $items, 'recent' => $recent]);
    }

    public function news(Request $request, ?string $slug = null): Response
    {
        $scope = MemberScope::fromRequest($request, $slug);

        $posts = Post::whereIn('club_id', $scope->clubIds())
            ->published()
            ->visibleTo($scope->user)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'club' => ['name' => $scope->clubFor($post->club_id)->name, 'slug' => $scope->clubFor($post->club_id)->slug],
                'title' => $post->title,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($post->excerpt ?: $post->content))), 200),
                'at' => ($post->published_at ?? $post->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Members/News', [...$this->scopeProps($scope), 'posts' => $posts]);
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
                'club' => ['name' => $scope->clubFor($subscription->club_id)->name, 'slug' => $scope->clubFor($subscription->club_id)->slug],
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
                'club' => ['name' => $scope->clubFor($invoice->club_id)->name, 'slug' => $scope->clubFor($invoice->club_id)->slug],
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
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function serialise(array $item): array
    {
        return [...$item, 'start' => $item['start']->toIso8601String(), 'end' => $item['end']->toIso8601String()];
    }
}
