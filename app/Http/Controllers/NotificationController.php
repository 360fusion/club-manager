<?php

namespace App\Http\Controllers;

use App\Services\MemberFeed;
use App\Support\MemberScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const CATEGORIES = ['summons', 'news', 'event', 'newsletter', 'reminder', 'notice', 'membership', 'signature', 'passed_on'];

    /** The inbox category for each kind of item in the lodges' feed. */
    private const FEED_CATEGORIES = ['news' => 'news', 'event' => 'event', 'update' => 'notice', 'newsletter' => 'newsletter'];

    private const PER_PAGE = 20;

    /**
     * The inbox: notifications sent to the member, and beside them what their lodges have put out (news, events,
     * notices, newsletters), so nothing is missed because no notification was sent for it. Lodge items are never
     * unread, so "Unread only" shows notifications alone.
     */
    public function index(Request $request, MemberFeed $feed): Response
    {
        $user = $request->user();
        $category = in_array($request->query('category'), self::CATEGORIES, true) ? $request->query('category') : '';
        $unreadOnly = $request->boolean('unread');

        $notifications = $user->notifications()
            ->when($category, fn ($query) => $query->where('data->category', $category))
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $n) => $this->present($n));

        // An item that already reached the member as a notification is listed once, as that notification.
        $sent = $notifications->flatMap(fn (array $n) => [$n['url'] ?? '', ($n['club']['slug'] ?? '').'|'.($n['title'] ?? '')])->filter()->flip();

        $lodgeItems = $unreadOnly ? collect() : collect($feed->items($user, MemberScope::for($user)->memberClubs, 50))
            ->map(fn (array $item) => [
                'id' => $item['key'],
                'feed' => true,
                'read' => true,
                'at' => $item['at'],
                'category' => self::FEED_CATEGORIES[$item['type']] ?? 'news',
                'title' => $item['title'],
                'body' => $item['excerpt'],
                'important' => $item['important'],
                'club' => ['name' => $item['club']['name'], 'slug' => $item['club']['slug']],
                'url' => route($item['link']['name'], $item['link']['params'], false),
            ])
            ->filter(fn (array $item) => (! $category || $item['category'] === $category)
                && ! $sent->has($item['url']) && ! $sent->has($item['club']['slug'].'|'.$item['title']));

        $all = $notifications->concat($lodgeItems)->sortByDesc('at')->values();
        $page = max(1, (int) $request->query('page', 1));

        return Inertia::render('Members/Notifications', [
            'notifications' => new LengthAwarePaginator($all->forPage($page, self::PER_PAGE)->values(), $all->count(), self::PER_PAGE, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'unreadCount' => $user->unreadNotifications()->count(),
            'filters' => ['category' => $category, 'unread' => $unreadOnly],
        ]);
    }

    /**
     * Mark one notification read, then go to the thing it is about.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect(is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('members.dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'read' => $notification->read_at !== null,
            'at' => $notification->created_at->toIso8601String(),
            ...$notification->data,
        ];
    }
}
