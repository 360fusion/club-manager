<?php

namespace App\Domains\ClubAccounting\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Resigned = 'resigned';
    case Honorary = 'honorary';
    case ExcludedRule181 = 'excluded_rule_181';
    case Deceased = 'deceased';
    case Historical = 'historical';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active Member',
            self::Resigned => 'Resigned',
            self::Honorary => 'Honorary Member',
            self::ExcludedRule181 => 'Excluded (Rule 181)',
            self::Deceased => 'Deceased',
            self::Historical => 'Historical Member',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::Resigned => 'bg-slate-100 text-slate-700 border-slate-200',
            self::Honorary => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
            self::ExcludedRule181 => 'bg-rose-100 text-rose-800 border-rose-300',
            self::Deceased => 'bg-purple-100 text-purple-800 border-purple-200',
            self::Historical => 'bg-slate-100 text-slate-800 border-slate-300 font-medium',
        };
    }

    public function isSubscribing(): bool
    {
        return in_array($this, [self::Active, self::Honorary]);
    }
}
