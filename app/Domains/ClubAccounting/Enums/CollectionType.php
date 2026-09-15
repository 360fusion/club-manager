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
            self::AlmsPlate => 'bg-amber-50 text-amber-800 border-amber-300',
            self::Raffle => 'bg-blue-50 text-blue-800 border-blue-300',
            self::CharityBox => 'bg-purple-50 text-purple-800 border-purple-300',
            self::Envelope => 'bg-emerald-50 text-emerald-800 border-emerald-300',
        };
    }
}
