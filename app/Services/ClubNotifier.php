<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a club's notifications to its active members.
 */
class ClubNotifier
{
    /**
     * Notify every active member of the club, except the person who triggered it.
     */
    public function toMembers(Club $club, ClubNotification $notification, ?User $except = null): void
    {
        $members = $club->users()
            ->wherePivot('status', 'active')
            ->when($except, fn ($query) => $query->where('users.id', '!=', $except->id))
            ->get();

        Notification::sendNow($members, $notification);
    }

    public function toUser(User $user, ClubNotification $notification): void
    {
        $user->notifyNow($notification);
    }
}
