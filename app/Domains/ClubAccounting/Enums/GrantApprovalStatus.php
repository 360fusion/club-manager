<?php

namespace App\Domains\ClubAccounting\Enums;

enum GrantApprovalStatus: string
{
    case Proposed = 'proposed';
    case CommitteeApproved = 'committee_approved';
    case LodgeVoted = 'lodge_voted';
    case Disbursed = 'disbursed';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::CommitteeApproved => 'Committee Approved',
            self::LodgeVoted => 'Open Lodge Voted',
            self::Disbursed => 'Disbursed / Paid',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Proposed => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700',
            self::CommitteeApproved => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::LodgeVoted => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::Disbursed => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
        };
    }
}
