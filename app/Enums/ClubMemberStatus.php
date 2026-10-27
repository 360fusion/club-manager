<?php

namespace App\Enums;

/**
 * Where a person stands in one club (the `status` on the club_user table): a pending request to join, an active
 * member, someone who has left, or someone paused or removed by an admin.
 */
enum ClubMemberStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Past = 'past';
    case Inactive = 'inactive';
    case Deleted = 'deleted';
}
