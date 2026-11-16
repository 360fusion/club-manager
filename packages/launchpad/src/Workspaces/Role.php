<?php

namespace Launchpad\Workspaces;

/** What a person is in a workspace. Abilities are decided here, in one place, never by comparing role names elsewhere. */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Manager => __('Manager'),
            self::Member => __('Member'),
        };
    }

    /** @return list<Role> the roles this one may change the permissions of: an owner all but owner, an admin the two below, nobody else any */
    public function editableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Admin, self::Manager, self::Member],
            self::Admin => [self::Manager, self::Member],
            default => [],
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => __('Can do everything, always.'),
            self::Admin => __('Runs the team day to day.'),
            self::Manager => __('Looks after the work and the people under them.'),
            self::Member => __('Basic use of the team.'),
        };
    }

    /** The roles an owner or admin can hand out: ownership moves only by a transfer */
    public static function assignable(): array
    {
        return [self::Admin, self::Manager, self::Member];
    }

    public function can(Ability $ability): bool
    {
        return match ($ability) {
            Ability::View => true,
            Ability::ViewMembers => $this !== self::Member,
            // the same roles the matrix shows: config launchpad.workspaces.permissions, so editing it changes what is enforced too
            Ability::ManageMembers, Ability::ManageBilling => $this === self::Owner || in_array($this->value, (array) config("launchpad.workspaces.permissions.{$ability->value}.2", []), true),
            Ability::ManageWorkspace => in_array($this, [self::Owner, self::Admin], true),
            Ability::Transfer, Ability::Delete => $this === self::Owner,
        };
    }

    /** Whether this role may change or remove a member holding $other: nobody manages an owner, and an admin does not manage another admin */
    public function outranks(self $other): bool
    {
        return match ($this) {
            self::Owner => $other !== self::Owner,
            self::Admin => in_array($other, [self::Manager, self::Member], true),
            // only matters for a manager who has been given the manage-members permission
            self::Manager => $other === self::Member,
            default => false,
        };
    }
}
