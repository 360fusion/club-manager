<?php

namespace App\Domains\ClubAccounting\Enums;

enum SubscriptionStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Waived = 'waived';
    case ArrearsWarning = 'arrears_warning';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid in Full',
            self::Waived => 'Dues Waived',
            self::ArrearsWarning => 'Rule 181 Arrears Warning',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::PartiallyPaid => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::Paid => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
            self::Waived => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700',
            self::ArrearsWarning => 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60 font-black animate-pulse',
        };
    }

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Unpaid, self::PartiallyPaid, self::ArrearsWarning]);
    }

    public function isArrears(): bool
    {
        return $this === self::ArrearsWarning;
    }
}
