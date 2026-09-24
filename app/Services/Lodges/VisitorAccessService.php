<?php

namespace App\Services\Lodges;

use App\Models\Club;
use App\Models\ClubVisitorAccess;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Support\ClubAccess;
use App\Support\VisitorSummons;
use Illuminate\Validation\ValidationException;

/**
 * A visiting brother asks a lodge to share its summonses; the lodge's secretary answers.
 */
class VisitorAccessService
{
    public function __construct(private readonly ClubNotifier $notifier) {}

    /**
     * @param  array{home_lodge_name: string, home_lodge_number?: ?string, rank?: ?string, message?: ?string}  $data
     */
    public function request(Club $club, User $user, array $data): ClubVisitorAccess
    {
        if (VisitorSummons::visibility($club) !== VisitorSummons::APPROVED_VISITORS) {
            throw ValidationException::withMessages(['access' => 'This lodge is not taking requests to receive its summonses.']);
        }

        if (ClubAccess::isActiveMember($user, $club)) {
            throw ValidationException::withMessages(['access' => 'You are already a member of this lodge.']);
        }

        $existing = ClubVisitorAccess::where('club_id', $club->id)->where('user_id', $user->id)->first();

        if ($existing?->status === ClubVisitorAccess::PENDING) {
            throw ValidationException::withMessages(['access' => 'Your request is waiting for the lodge to answer.']);
        }

        if ($existing?->status === ClubVisitorAccess::APPROVED) {
            throw ValidationException::withMessages(['access' => 'You already receive this lodge\'s summonses.']);
        }

        $access = $existing ?? new ClubVisitorAccess(['club_id' => $club->id, 'user_id' => $user->id]);
        $access->fill($data);
        $access->forceFill(['status' => ClubVisitorAccess::PENDING, 'decided_by' => null, 'decided_at' => null])->save();

        $this->notifier->toStaff($club, ClubNotification::visitorRequested($access->load('user'), $club), 'manage_meetings');

        return $access;
    }

    public function withdraw(ClubVisitorAccess $access): void
    {
        if ($access->status === ClubVisitorAccess::PENDING) {
            $access->delete();
        }
    }

    public function approve(ClubVisitorAccess $access, User $by): void
    {
        $this->decide($access, $by, ClubVisitorAccess::APPROVED, [ClubVisitorAccess::PENDING, ClubVisitorAccess::DECLINED, ClubVisitorAccess::REVOKED]);
    }

    public function decline(ClubVisitorAccess $access, User $by): void
    {
        $this->decide($access, $by, ClubVisitorAccess::DECLINED, [ClubVisitorAccess::PENDING]);
    }

    public function revoke(ClubVisitorAccess $access, User $by): void
    {
        $this->decide($access, $by, ClubVisitorAccess::REVOKED, [ClubVisitorAccess::APPROVED]);
    }

    /**
     * @param  list<string>  $from
     */
    private function decide(ClubVisitorAccess $access, User $by, string $status, array $from): void
    {
        if (! in_array($access->fresh()->status, $from, true)) {
            throw ValidationException::withMessages(['access' => 'That request has already been dealt with.']);
        }

        $access->forceFill(['status' => $status, 'decided_by' => $by->id, 'decided_at' => now()])->save();

        if ($status !== ClubVisitorAccess::REVOKED) {
            $this->notifier->toUser($access->user, ClubNotification::visitorDecided($access->load('club.lodge'), $access->club));
        }
    }
}
