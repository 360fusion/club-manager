<?php

namespace App\Listeners;

use App\Jobs\SendPushNotification;
use App\Models\User;
use App\Support\NativeApp;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/** Everything that lands in a member's inbox also goes to their phones, if they have the app. */
class PushDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User || ! $event->response instanceof DatabaseNotification) {
            return;
        }

        $data = $event->response->data;

        if (in_array($data['category'] ?? null, $event->notifiable->push_muted ?? [], true) || ! $event->notifiable->pushDevices()->exists()) {
            return;
        }

        SendPushNotification::dispatch(
            $event->notifiable->id,
            (string) ($data['title'] ?? 'Club Manager'),
            (string) ($data['body'] ?? ''),
            // Opening it goes through the inbox, so it is marked read.
            NativeApp::safePath(route('members.notifications.open', ['id' => $event->response->id], false)),
        );
    }
}
