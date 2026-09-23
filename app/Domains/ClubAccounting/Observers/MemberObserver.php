<?php

namespace App\Domains\ClubAccounting\Observers;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationService;

/**
 * Keeps a member's portal account in step with their record, wherever the record is changed from.
 */
class MemberObserver
{
    public function __construct(private MemberInvitationService $invitations) {}

    public function updated(Member $member): void
    {
        if ($member->wasChanged('membership_status') && ! in_array($member->membership_status, [MembershipStatus::Active, MembershipStatus::Honorary, MembershipStatus::Historical], true)) {
            $this->invitations->memberLeft($member);

            return;
        }

        if ($member->wasChanged('email')) {
            $this->invitations->emailChanged($member);
        }
    }

    public function deleting(Member $member): void
    {
        $this->invitations->memberLeft($member, endAccess: false);
    }
}
