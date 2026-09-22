<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const CATEGORIES = ['summons', 'news', 'event', 'notice', 'membership', 'signature'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->query('category');
        $unreadOnly = $request->boolean('unread');

        $notifications = $user->notifications()
            ->when(in_array($category, self::CATEGORIES, true), fn ($query) => $query->where('data->category', $category))
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => $this->present($n));

        return Inertia::render('Members/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filters' => ['category' => in_array($category, self::CATEGORIES, true) ? $category : '', 'unread' => $unreadOnly],
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
