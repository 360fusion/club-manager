<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Support\ClubAccess;
use App\Support\VisitorSummons;
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

    /**
     * Notify the club's people who hold a capability, such as the secretary for visitor requests.
     */
    public function toStaff(Club $club, ClubNotification $notification, string $capability): void
    {
        $staff = $club->users()->wherePivot('status', 'active')->get()
            ->filter(fn (User $user) => ClubAccess::can($user, $club, $capability));

        Notification::sendNow($staff, $notification);
    }

    /**
     * Tell the people who follow the club's lodge listing, and asked to hear about summonses, that
     * one has been published. Members already had their own notification, and anyone who is not
     * allowed to see this lodge's meetings is not told about them.
     */
    public function toFollowers(Club $club, Meeting $meeting): void
    {
        $lodge = $club->lodge;

        if ($lodge === null) {
            return;
        }

        $followers = $lodge->followers()->wherePivot('notify_summons', true)->get()
            ->reject(fn (User $user) => ClubAccess::isActiveMember($user, $club))
            ->filter(fn (User $user) => VisitorSummons::canSee($club, $user));

        Notification::sendNow($followers, ClubNotification::followedSummons($meeting, $club, $lodge->slug));
    }
}
