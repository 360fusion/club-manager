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
            self::Active => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60',
            self::Resigned => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800',
            self::Honorary => 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60 font-bold',
            self::ExcludedRule181 => 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60',
            self::Deceased => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
            self::Historical => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-slate-300 dark:border-slate-700 font-medium',
        };
    }

    public function isSubscribing(): bool
    {
        return in_array($this, [self::Active, self::Honorary]);
    }
}
