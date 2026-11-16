<?php

namespace Launchpad\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The in-app notice: one line in the bell and on the Notifications page. Send one with `Notice::to($user, 'Title', 'What happened', '/where/to/go')`.
 *
 * An app's own Laravel notification shows up there too when it uses the `database` channel and its toArray() returns `title`, `message` and
 * (optionally) `url`, the address a click goes to. Nothing else is needed.
 */
class Notice extends Notification
{
    public function __construct(public string $title, public string $message = '', public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, message: string, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url];
    }

    public static function to(?object $user, string $title, string $message = '', ?string $url = null): void
    {
        if ($user && method_exists($user, 'notify') && config('launchpad.modules.notifications')) {
            $user->notify(new static($title, $message, $url));
        }
    }
}
