<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in member's home: an overview of every club they belong to,
 * what needs their attention, what is coming up and the latest club news.
 */
class MemberHomeController extends Controller
{
    private const FEED_LIMIT = 20;

    private const UP_NEXT_LIMIT = 6;

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = $user->clubs()->with('clubType')->get();
        $clubs = $memberships->filter(fn (Club $club) => $club->pivot->status === 'active')->values();
        $pending = $memberships->filter(fn (Club $club) => $club->pivot->status === 'pending')->values();

        return Inertia::render('Members/Home', [
            'clubs' => $clubs->map(fn (Club $club) => $this->clubSummary($club))->all(),
            'pendingClubs' => $pending->map(fn (Club $club) => $this->clubSummary($club))->all(),
            'attention' => $this->attention($user, $clubs),
            'upNext' => $this->upNext($user, $clubs),
            'feed' => Inertia::defer(fn () => $this->feed($user, $clubs)),
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
            'type_name' => $club->clubType?->name,
            'role' => $role,
            'member_number' => $club->pivot->member_number ?? '',
            'is_staff' => $role !== 'member',
        ];
    }

    /**
     * Published summonses the member has not answered yet and can still answer.
     *
     * @param  Collection<int, Club>  $clubs
     * @return list<array<string, mixed>>
     */
    private function attention(User $user, Collection $clubs): array
    {
        if ($clubs->isEmpty()) {
            return [];
        }

        $meetings = Meeting::whereIn('club_id', $clubs->pluck('id'))
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', today())
            ->where(fn ($cutoff) => $cutoff->whereNull('rsvp_cutoff_at')->orWhere('rsvp_cutoff_at', '>', now()))
            ->orderBy('meeting_date')
            ->get();

        $answered = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $user->id)
            ->whereNotNull('responded_at')
            ->pluck('meeting_id');

        return $meetings
            ->reject(fn (Meeting $meeting) => $answered->contains($meeting->id))
            ->map(fn (Meeting $meeting) => [
                'type' => 'meeting_rsvp',
                'club_slug' => $clubs->firstWhere('id', $meeting->club_id)->slug,
                'club_name' => $clubs->firstWhere('id', $meeting->club_id)->name,
                'title' => $meeting->title,
                'date' => $meeting->meeting_date?->toDateString(),
                'cutoff' => $meeting->rsvp_cutoff_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * The next meetings and events across the member's clubs, soonest first.
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

        $meetings = Meeting::whereIn('club_id', $clubIds)
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', today())
            ->orderBy('meeting_date')
            ->limit(self::UP_NEXT_LIMIT)
            ->get();

        $meetingReplies = MeetingRsvp::whereIn('meeting_id', $meetings->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('attendance_status', 'meeting_id');

        $events = Event::whereIn('club_id', $clubIds)
            ->visibleTo($user)
            ->where('status', 'upcoming')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(self::UP_NEXT_LIMIT)
            ->get();

        $eventReplies = DB::table('event_user')
            ->whereIn('event_id', $events->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('attendance_status', 'event_id');

        return $meetings->map(fn (Meeting $meeting) => [
            'type' => 'meeting',
            'club_slug' => $clubs->firstWhere('id', $meeting->club_id)->slug,
            'club_name' => $clubs->firstWhere('id', $meeting->club_id)->name,
            'title' => $meeting->title,
            'at' => ($meeting->starts_at ? $meeting->meeting_date?->copy()->setTimeFromTimeString((string) $meeting->starts_at) : $meeting->meeting_date)?->toIso8601String(),
            'where' => $meeting->venue,
            'reply' => $meetingReplies[$meeting->id] ?? null,
        ])->concat($events->map(fn (Event $event) => [
            'type' => 'event',
            'club_slug' => $clubs->firstWhere('id', $event->club_id)->slug,
            'club_name' => $clubs->firstWhere('id', $event->club_id)->name,
            'title' => $event->title,
            'at' => $event->starts_at?->toIso8601String(),
            'where' => $event->location,
            'reply' => $eventReplies[$event->id] ?? null,
        ]))
            ->sortBy('at')
            ->take(self::UP_NEXT_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Recent news, events, updates and newsletters from the member's clubs.
     *
     * @param  Collection<int, Club>  $clubs
     * @return list<array<string, mixed>>
     */
    private function feed(User $user, Collection $clubs): array
    {
        if ($clubs->isEmpty()) {
            return [];
        }

        $clubIds = $clubs->pluck('id');
        $club = fn (int $id): array => ['name' => $clubs->firstWhere('id', $id)->name, 'slug' => $clubs->firstWhere('id', $id)->slug];
        $roles = $clubs->mapWithKeys(fn (Club $c) => [$c->id => $c->pivot->role ?? 'member']);

        $posts = Post::whereIn('club_id', $clubIds)->published()->visibleTo($user)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->limit(self::FEED_LIMIT)->get()
            ->map(fn (Post $post) => [
                'key' => 'post-'.$post->id,
                'type' => 'news',
                'club' => $club($post->club_id),
                'title' => $post->title,
                'excerpt' => $this->excerpt($post->excerpt ?: $post->content),
                'at' => ($post->published_at ?? $post->created_at)->toIso8601String(),
                'important' => false,
                'link' => ['name' => 'member.posts.show', 'params' => ['slug' => $club($post->club_id)['slug'], 'id' => $post->id]],
            ]);

        $events = Event::whereIn('club_id', $clubIds)->visibleTo($user)
            ->where('status', 'upcoming')
            ->where('starts_at', '>=', now())
            ->latest()->limit(self::FEED_LIMIT)->get()
            ->map(fn (Event $event) => [
                'key' => 'event-'.$event->id,
                'type' => 'event',
                'club' => $club($event->club_id),
                'title' => $event->title,
                'excerpt' => trim($event->starts_at?->format('j M Y, H:i').' · '.$event->location, ' ·'),
                'at' => $event->created_at->toIso8601String(),
                'important' => false,
                'link' => ['name' => 'member.events', 'params' => ['slug' => $club($event->club_id)['slug']]],
            ]);

        $updates = ClubUpdate::whereIn('club_id', $clubIds)->where('status', 'sent')
            ->latest('sent_at')->limit(self::FEED_LIMIT)->get()
            ->map(fn (ClubUpdate $update) => [
                'key' => 'update-'.$update->id,
                'type' => 'update',
                'club' => $club($update->club_id),
                'title' => $update->title,
                'excerpt' => $this->excerpt($update->summary),
                'at' => ($update->sent_at ?? $update->created_at)->toIso8601String(),
                'important' => (bool) $update->is_important,
                'link' => ['name' => 'member.dashboard', 'params' => ['slug' => $club($update->club_id)['slug']]],
            ]);

        $newsletters = Newsletter::whereIn('club_id', $clubIds)->where('status', 'sent')
            ->latest('sent_at')->limit(self::FEED_LIMIT)->get()
            ->filter(fn (Newsletter $n) => empty($n->target_roles) || in_array($roles[$n->club_id], $n->target_roles, true))
            ->map(fn (Newsletter $newsletter) => [
                'key' => 'newsletter-'.$newsletter->id,
                'type' => 'newsletter',
                'club' => $club($newsletter->club_id),
                'title' => $newsletter->subject,
                'excerpt' => $this->excerpt($newsletter->content),
                'at' => ($newsletter->sent_at ?? $newsletter->created_at)->toIso8601String(),
                'important' => false,
                'link' => ['name' => 'member.dashboard', 'params' => ['slug' => $club($newsletter->club_id)['slug']]],
            ]);

        return $posts->concat($events)->concat($updates)->concat($newsletters)
            ->sortBy([['important', 'desc'], ['at', 'desc']])
            ->take(self::FEED_LIMIT)
            ->values()
            ->all();
    }

    private function excerpt(?string $html): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $html))), 160);
    }
}
