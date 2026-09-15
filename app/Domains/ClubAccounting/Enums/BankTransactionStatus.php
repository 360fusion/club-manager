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
            self::Unmatched => 'bg-amber-50 text-amber-800 border-amber-300',
            self::Matched => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::Ignored => 'bg-slate-100 text-slate-600 border-slate-300',
        };
    }
}
