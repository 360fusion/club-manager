<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Notifications\Bell;

/** The Notifications page (everything the bell shows, and the rest) and what a person does with a notification */
class InboxController extends Controller
{
    public function index(Request $request): View|Responsable
    {
        $page = $request->user()->notifications()->paginate(20);
        $rows = $page->getCollection()->map(Bell::row(...));

        return Ui::render('notifications.index', ['rows' => $rows, 'page' => $page, 'unread' => $request->user()->unreadNotifications()->count()], fn () => [
            'notifications' => $rows->map(fn ($r) => ['id' => $r['id'], 'title' => $r['title'], 'message' => $r['message'], 'url' => $r['url'], 'read' => $r['read'], 'at' => $r['at']?->toIso8601String()])->values(),
            'unread' => $request->user()->unreadNotifications()->count(),
            'nextPageUrl' => $page->nextPageUrl(),
            'prevPageUrl' => $page->previousPageUrl(),
        ]);
    }

    /** A click on a notification: it is read, and the person goes where it points (only to a page of this site) */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        $local = str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\');
        $sameSite = $url !== '' && parse_url($url, PHP_URL_HOST) === $request->getHost();

        return redirect($local || $sameSite ? $url : route('notifications.index'));
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back();
    }
}
