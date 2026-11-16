<?php

namespace Launchpad\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** What the header's bell shows, and how a notification becomes a plain row for a page */
final class Bell
{
    private static ?bool $ready = null;

    public static function enabled(?object $user): bool
    {
        return $user !== null && config('launchpad.modules.notifications') && method_exists($user, 'notifications')
            && (self::$ready ??= Schema::hasTable('notifications'));
    }

    /** @return array{unread: int, latest: list<array<string, mixed>>} */
    public static function summary(object $user, int $limit = 6): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit($limit)->get()->map(self::row(...))->all(),
        ];
    }

    /** @return array{id: string, title: string, message: string, url: ?string, read: bool, at: Carbon|null} */
    public static function row(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? __('Notification')),
            'message' => (string) ($data['message'] ?? ''),
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'at' => $notification->created_at,
        ];
    }
}
