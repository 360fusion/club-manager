<?php

namespace App\Domains\ClubAccounting\Enums;

enum CollectionType: string
{
    case AlmsPlate = 'alms_plate';
    case Raffle = 'raffle';
    case CharityBox = 'charity_box';
    case Envelope = 'envelope';

    public function label(): string
    {
        return match ($this) {
            self::AlmsPlate => 'Alms Plate Collection',
            self::Raffle => 'Festive Board Raffle',
            self::CharityBox => 'Charity Box Takings',
            self::Envelope => 'Gift Aid Envelopes',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::AlmsPlate => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::Raffle => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::CharityBox => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::Envelope => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
        };
    }
}
