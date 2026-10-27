<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Newsletter;
use App\Models\NewsTag;
use App\Models\Post;
use App\Services\Events\EventPayload;
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
        $club = Club::where('slug', $slug)
            ->with(['membershipPlans', 'events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        // Get user's membership pivot status
        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;
        $isPending = $memberPivot && $memberPivot->status === 'pending';

        // The club root is the member area. Everyone else sees the public website.
        if ($memberPivot === null && ! $user?->is_super_admin) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Attendance stats
        $userRsvps = EventRegistration::with('attendees')
            ->where('user_id', $user?->id ?? 0)
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->get();

        $totalRsvps = $userRsvps->count();
        $attendedCount = $userRsvps->where('status', 'attending')->count() + $userRsvps->filter(fn ($r) => $r->booker()?->checked_in_at !== null)->count();
        $attendanceRate = $totalRsvps > 0 ? round(($attendedCount / $totalRsvps) * 100, 1) : 100.0;

        // Published Meetings & Summons for the member
        $meetings = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(function ($meeting) use ($user) {
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
                    'is_cutoff_passed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
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
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->orderBy('starts_at', 'asc')
            ->get();
        $eventRegistrations = EventPayload::registrationsFor($user, $eventModels->pluck('id'));
        $events = $eventModels->map(fn (Event $event) => EventPayload::forMember($event, $eventRegistrations->get($event->id)));

        // Published Newsletters for member role
        $role = $memberPivot->role ?? 'member';
        $newsletters = Newsletter::where('club_id', $club->id)
            ->where('status', 'sent')
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

        return Inertia::render('Member/Dashboard', [
            'club' => $club,
            'isPending' => $isPending,
            'memberRole' => $role,
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
            'attendanceRate' => $attendanceRate,
            'attendedCount' => $attendedCount,
            'totalRsvps' => $totalRsvps,
            'meetings' => $meetings,
            'events' => $events,
            'newsletters' => $newsletters,
        ]);
    }

    /**
     * Display member events & RSVPs page inside member portal.
     */
    public function events(Request $request, string $slug): Response|RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with(['events.menuItems', 'events.ticketTiers'])
            ->firstOrFail();

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        if ($memberPivot === null && ! $user?->is_super_admin) {
            return redirect()->route('public.site', ['clubSlug' => $club->slug]);
        }

        // Published Meetings & Summons for the member
        $meetings = Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(function ($meeting) use ($user) {
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
                    'is_cutoff_passed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
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
            'club' => $club,
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
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'isMember' => $memberPivot !== null || (bool) $user?->is_super_admin,
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
