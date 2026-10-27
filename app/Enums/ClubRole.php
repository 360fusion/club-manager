<?php

namespace App\Enums;

/**
 * A person's role in one club (the `role` on the club_user table). Which role may do what is set per capability in
 * config/club_permissions.php; this is only the list of valid values, so a misspelt role is a code error and not a
 * silent "no access".
 */
enum ClubRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Treasurer = 'treasurer';
    case Coach = 'coach';
    case Member = 'member';
    case Examiner = 'examiner';
    case Secretary = 'secretary';
    case Visitor = 'visitor';

    /**
     * The roles an admin can give someone from the member list.
     *
     * @return list<string>
     */
    public static function assignable(): array
    {
        return array_map(
            fn (self $role) => $role->value,
            [self::Owner, self::Admin, self::Coach, self::Member, self::Treasurer, self::Examiner],
        );
    }
}
