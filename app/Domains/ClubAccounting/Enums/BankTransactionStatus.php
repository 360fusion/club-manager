<?php

namespace App\Domains\ClubAccounting\Enums;

enum BankTransactionStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched / Pending',
            self::Matched => 'Reconciled / Matched',
            self::Ignored => 'Ignored',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unmatched => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::Matched => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
            self::Ignored => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700',
        };
    }
}
