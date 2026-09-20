<?php

namespace App\Enums;

use App\Models\User;

/**
 * Who may see (or respond to) a piece of club content.
 *
 * The cases run from narrowest to widest, so an audience that covers a wider
 * one always covers the narrower ones too.
 */
enum Visibility: string
{
    case Club = 'club';
    case Network = 'network';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Club => 'Club members only',
            self::Network => 'Members of any club',
            self::Public => 'Public',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Club => 'Only active members of this club.',
            self::Network => 'Any signed-in member of a club on the platform.',
            self::Public => 'Anyone, including visitors who are not signed in.',
        };
    }

    /**
     * Higher means a wider audience.
     */
    public function breadth(): int
    {
        return match ($this) {
            self::Club => 1,
            self::Network => 2,
            self::Public => 3,
        };
    }

    public function isNoBroaderThan(self $other): bool
    {
        return $this->breadth() <= $other->breadth();
    }

    /**
     * Whether this audience includes the given viewer for content owned by a club.
     */
    public function includes(?User $viewer, int $clubId): bool
    {
        if ($this === self::Public) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        if ($viewer->is_super_admin) {
            return true;
        }

        return match ($this) {
            self::Network => $viewer->activeClubIds()->isNotEmpty(),
            self::Club => $viewer->activeClubIds()->contains($clubId),
            self::Public => true,
        };
    }

    /**
     * Options for select inputs on the admin forms.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
