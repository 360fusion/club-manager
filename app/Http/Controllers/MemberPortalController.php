<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Club;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Newsletter;
use App\Models\NewsTag;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Services\Events\EventPayload;
use App\Services\LodgeAccountsOverview;
use App\Services\Meetings\MeetingCollections;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberPortalController extends Controller
{
    /**
     * Display member portal dashboard.
     */
    public function show(string $slug): Response|RedirectResponse
    {
        $user = Auth::user();
        $club = $this->findClub($slug);

        // Get user's membership pivot status
        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;
        $isPending = $memberPivot && $memberPivot->status === 'pending';

        // The club root is the member area. Everyone else, and past members, see the public website.
        if (($memberPivot === null || ! in_array($memberPivot->status, ['active', 'pending'], true)) && ! $user?->actsAsSuperAdmin()) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Someone still waiting for approval sees the waiting notice and none of the lodge's summonses, events or circulars.
        $meetingModels = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->when($isPending, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('meeting_date', 'asc')
            ->get();
        $myMeetingRsvps = $user
            ? MeetingRsvp::whereIn('meeting_id', $meetingModels->pluck('id'))->where('user_id', $user->id)->with('guests')->get()->keyBy('meeting_id')
            : collect();

        $meetings = $meetingModels
            ->map(function ($meeting) use ($myMeetingRsvps, $club) {
                $userRsvp = $myMeetingRsvps->get($meeting->id);

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meeting_date' => Carbon::parse($meeting->meeting_date)->format('j M Y'),
                    'raw_meeting_date' => $meeting->meeting_date,
                    'starts_at' => $meeting->starts_at,
                    'rehearsal_starts_at' => $meeting->rehearsal_starts_at,
                    'venue' => $meeting->venue,
                    'dress_code' => $meeting->dress_code,
                    'rsvp_cutoff_at' => $meeting->rsvp_cutoff_at ? Carbon::parse($meeting->rsvp_cutoff_at)->format('j M Y, H:i') : null,
                    'is_cutoff_passed' => $club->hasPassed($meeting->rsvp_cutoff_at),
                    'dining_cost_member' => number_format((float) $meeting->dining_cost_member, 2),
                    'user_rsvp' => $userRsvp ? [
                        'attendance_status' => $userRsvp->attendance_status,
                        'dietary_requirements' => $userRsvp->dietary_requirements,
                        'apology_reason' => $userRsvp->apology_reason,
                        'payment_status' => $userRsvp->payment_status ?? 'unpaid',
                        'payment_reference' => $userRsvp->payment_reference,
                        'guests' => $userRsvp->guests,
                    ] : null,
                ];
            });

        // Upcoming Events
        $eventModels = Event::where('club_id', $club->id)
            ->published()
            ->visibleTo($user)
            ->when($isPending, fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->orderBy('starts_at', 'asc')
            ->get();
        $eventRegistrations = EventPayload::registrationsFor($user, $eventModels->pluck('id'));
        $events = $eventModels->map(fn (Event $event) => EventPayload::forMember($event, $eventRegistrations->get($event->id)));

        // Published Newsletters for member role
        $role = $memberPivot->role ?? 'member';
        $newsletters = Newsletter::where('club_id', $club->id)
            ->where('status', 'sent')
            ->when($isPending, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('sent_at')
            ->get()
            ->filter(function ($n) use ($role) {
                $target = $n->target_roles ?? [];

                return empty($target) || in_array($role, $target);
            })
            ->map(fn ($n) => [
                'id' => $n->id,
                'subject' => $n->subject,
                'content' => $n->content,
                'sent_at' => $n->sent_at?->format('j M Y, H:i'),
            ])
            ->values();

        // What the member still owes this lodge in dues, so the dashboard never claims good standing it cannot know.
        $subscriptions = MemberSubscription::where('club_id', $club->id)
            ->whereHas('member', fn ($member) => $member->where('user_id', $user->id))
            ->orderByDesc('due_date')
            ->get();
        $duesOwed = $subscriptions
            ->filter(fn (MemberSubscription $subscription) => $subscription->status->isOutstanding())
            ->sum('balance_due');
        $latestSubscription = $subscriptions->first();

        $member = Member::where('club_id', $club->id)->where('user_id', $user->id)->first();

        return Inertia::render('Member/Dashboard', [
            'club' => [...$club->only(['id', 'name', 'slug', 'lodge_number']), 'meets' => $club->lodge?->meets_text],
            'isPending' => $isPending,
            'memberRole' => $role,
            'duesOwed' => round((float) $duesOwed, 2),
            'subscription' => $latestSubscription ? [
                'year' => $latestSubscription->billing_year,
                'amount_due' => (float) $latestSubscription->amount_due,
                'balance' => $latestSubscription->balance_due,
                'status' => $latestSubscription->status->value,
                'due_date' => $latestSubscription->due_date->format('j F Y'),
            ] : null,
            'organiserTools' => $isPending ? null : $this->organiserTools($user, $club, $member),
            'officers' => $isPending ? [] : Member::currentOfficers($club),
            'documents' => $isPending ? [] : $this->memberDocuments($club),
            'meetings' => $meetings,
            'events' => $events,
            'newsletters' => $newsletters,
        ]);
    }

    /**
     * The admin screens a lodge's staff reach from their lodge page, each only when their role may open it, and the
     * office they hold; null when they may open none of them.
     *
     * @return array{office: ?string, links: list<array{key: string, label: string, href: string}>}|null
     */
    private function organiserTools(?User $user, Club $club, ?Member $member): ?array
    {
        $links = [];

        if (ClubAccess::can($user, $club, 'manage_events')) {
            // The event happening today or next, else the last one held.
            $event = Event::where('club_id', $club->id)->published()->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->first()
                ?? Event::where('club_id', $club->id)->published()->latest('starts_at')->first();

            if ($event) {
                $links[] = ['key' => 'event_night', 'label' => 'Event night', 'href' => route('admin.events.checkin', ['clubSlug' => $club->slug, 'id' => $event->id])];
            }
        }

        if (ClubAccess::can($user, $club, 'manage_meetings')) {
            $meeting = Meeting::where('club_id', $club->id)->where('status', 'published')->where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->first()
                ?? Meeting::where('club_id', $club->id)->where('status', 'published')->latest('meeting_date')->first();

            if ($meeting) {
                $links[] = ['key' => 'catering', 'label' => 'Catering', 'href' => route('admin.meetings.dining', ['clubSlug' => $club->slug, 'id' => $meeting->id])];
            }
        }

        if (MeetingCollections::canRecord($user, $club)) {
            // Counted on the night: tonight's meeting or the last one held, else the next.
            $meeting = Meeting::where('club_id', $club->id)->where('status', 'published')->where('meeting_date', '<=', now()->toDateString())->latest('meeting_date')->first()
                ?? Meeting::where('club_id', $club->id)->where('status', 'published')->orderBy('meeting_date')->first();

            if ($meeting) {
                $links[] = ['key' => 'collections', 'label' => 'Alms and raffle', 'href' => route('member.meetings.collections', ['slug' => $club->slug, 'id' => $meeting->id])];
            }
        }

        if (ClubAccess::can($user, $club, 'manage_events')) {
            $links[] = ['key' => 'add_booking', 'label' => 'Add booking', 'href' => route('admin.events.index', ['clubSlug' => $club->slug])];
        }

        if (LodgeAccountsOverview::canView($user, $club)) {
            $links[] = ['key' => 'accounts', 'label' => 'Accounts', 'href' => route('member.accounts', ['slug' => $club->slug])];
        }

        if (ClubAccess::can($user, $club, 'send_newsletters')) {
            $links[] = ['key' => 'send_notice', 'label' => 'Send notice', 'href' => route('admin.notices.index', ['clubSlug' => $club->slug])];
        }

        if (PassOnController::canSend($user, $club)) {
            $links[] = ['key' => 'pass_on', 'label' => 'Pass on', 'href' => route('member.pass_on', ['slug' => $club->slug])];
        }

        if ($links === []) {
            return null;
        }

        $office = collect($member ? $member->active_offices : [])
            ->map(fn ($office) => $office instanceof LodgeOffice ? $office : LodgeOffice::tryFrom((string) $office))
            ->first(fn (?LodgeOffice $office) => $office && $office !== LodgeOffice::Member);

        return ['office' => $office?->label(), 'links' => $links];
    }

    /**
     * The files and links the lodge's website keeps for members only: downloads blocks marked members-only, or on a
     * members-only page.
     *
     * @return list<array{title: string, href: string, type: string}>
     */
    private function memberDocuments(Club $club): array
    {
        $documents = [];

        foreach (Page::where('club_id', $club->id)->live()->orderBy('sort_order')->get() as $page) {
            foreach ($page->blocks ?? [] as $block) {
                if (($block['type'] ?? null) !== 'downloads' || (empty($block['members_only']) && ! $page->is_members_only)) {
                    continue;
                }

                foreach ($block['items'] ?? [] as $item) {
                    $isUpload = ($item['source'] ?? null) === 'upload';
                    $href = $isUpload
                        ? ((int) ($item['media_id'] ?? 0) > 0 ? route('public.site.file', ['clubSlug' => $club->slug, 'mediaId' => (int) $item['media_id']]) : '')
                        : (string) ($item['url'] ?? '');

                    if ($href === '') {
                        continue;
                    }

                    $fileName = (string) ($item['file_name'] ?? '');
                    $documents[$href] = [
                        'title' => ($item['title'] ?? '') ?: ($fileName ?: 'Document'),
                        'href' => $href,
                        'type' => $isUpload ? strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) : 'Link',
                    ];
                }
            }
        }

        return array_values($documents);
    }

    /**
     * Display member events & RSVPs page inside member portal.
     */
    public function events(Request $request, string $slug): Response|RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        // Summonses and bookings are for active members only, like the other member pages.
        if (($memberPivot === null || $memberPivot->status !== 'active') && ! $user?->actsAsSuperAdmin()) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Published Meetings & Summons for the member
        $meetings = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(function ($meeting) use ($user, $club) {
                $userRsvp = $user ? MeetingRsvp::where('meeting_id', $meeting->id)
                    ->where('user_id', $user->id)
                    ->with('guests')
                    ->first() : null;

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meeting_date' => Carbon::parse($meeting->meeting_date)->format('j M Y'),
                    'raw_meeting_date' => $meeting->meeting_date,
                    'starts_at' => $meeting->starts_at,
                    'rehearsal_starts_at' => $meeting->rehearsal_starts_at,
                    'venue' => $meeting->venue,
                    'dress_code' => $meeting->dress_code,
                    'rsvp_cutoff_at' => $meeting->rsvp_cutoff_at ? Carbon::parse($meeting->rsvp_cutoff_at)->format('j M Y, H:i') : null,
                    'is_cutoff_passed' => $club->hasPassed($meeting->rsvp_cutoff_at),
                    'dining_cost_member' => number_format((float) $meeting->dining_cost_member, 2),
                    'user_rsvp' => $userRsvp ? [
                        'attendance_status' => $userRsvp->attendance_status,
                        'dietary_requirements' => $userRsvp->dietary_requirements,
                        'apology_reason' => $userRsvp->apology_reason,
                        'payment_status' => $userRsvp->payment_status ?? 'unpaid',
                        'payment_reference' => $userRsvp->payment_reference,
                        'guests' => $userRsvp->guests,
                    ] : null,
                ];
            });

        $eventModels = Event::where('club_id', $club->id)
            ->published()
            ->visibleTo($user)
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->orderBy('starts_at', 'asc')
            ->get();
        $eventRegistrations = EventPayload::registrationsFor($user, $eventModels->pluck('id'));
        $events = $eventModels->map(fn (Event $event) => EventPayload::forMember($event, $eventRegistrations->get($event->id)));

        return Inertia::render('Member/Events', [
            'club' => $club->only(['id', 'name', 'slug', 'lodge_number']),
            'memberRole' => $memberPivot->role ?? 'member',
            'meetings' => $meetings,
            'events' => $events,
        ]);
    }

    /**
     * Display a published news article inside the member portal.
     */
    public function showPost(string $slug, int $id): Response
    {
        $user = Auth::user();
        $club = $this->findClub($slug);
        $post = Post::where('club_id', $club->id)->published()->visibleTo($user)->with('tags:id,name,slug,color')->findOrFail($id);

        $others = fn () => Post::where('club_id', $club->id)->published()->visibleTo($user)->whereKeyNot($post->id);
        $newestFirst = 'COALESCE(posts.published_at, posts.created_at) DESC';
        $tagIds = $post->tags->pluck('id')->all();

        $latest = $others()->orderByRaw($newestFirst)->limit(5)->get();

        // Other items sharing at least one tag, most shared tags first, then newest.
        $related = $tagIds === [] ? collect() : $others()
            ->whereHas('tags', fn ($tags) => $tags->whereIn('news_tags.id', $tagIds))
            ->withCount(['tags as shared_tags' => fn ($tags) => $tags->whereIn('news_tags.id', $tagIds)])
            ->orderByDesc('shared_tags')
            ->orderByRaw($newestFirst)
            ->limit(5)
            ->get();

        $summary = fn (Post $item) => [
            'id' => $item->id,
            'title' => $item->title,
            'at' => ($item->published_at ?? $item->created_at)->toIso8601String(),
        ];

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        return Inertia::render('Member/PostShow', [
            'club' => $club->only(['id', 'name', 'slug', 'lodge_number']),
            'memberRole' => $memberPivot->role ?? 'member',
            'isMember' => $memberPivot !== null || (bool) $user?->actsAsSuperAdmin(),
            'lodgeSlug' => $club->lodge?->slug,
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'blocks' => $post->blocks ?? [],
                'attachments' => $post->attachments ?? [],
                'cover_image_url' => $post->cover_image_url,
                'published_at' => ($post->published_at ?? $post->created_at)?->format('j M Y'),
                'author_name' => $post->author?->name ?? 'Club Secretary',
                'tags' => $post->tags->map(fn (NewsTag $tag) => ['name' => $tag->name, 'slug' => $tag->slug, 'color' => $tag->color])->values(),
            ],
            'tagOptions' => NewsTag::browsableFor([$club->id], $user),
            'latest' => $latest->map($summary)->values(),
            'related' => $related->map($summary)->values(),
        ]);
    }
}
