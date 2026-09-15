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
            self::Unpaid => 'bg-amber-50 text-amber-800 border-amber-300',
            self::PartiallyPaid => 'bg-blue-50 text-blue-800 border-blue-300',
            self::Paid => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::Waived => 'bg-slate-100 text-slate-700 border-slate-300',
            self::ArrearsWarning => 'bg-rose-50 text-rose-800 border-rose-300 font-black animate-pulse',
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
