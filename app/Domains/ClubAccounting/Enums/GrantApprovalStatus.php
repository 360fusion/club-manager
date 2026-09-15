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
            self::Proposed => 'bg-slate-100 text-slate-700 border-slate-300',
            self::CommitteeApproved => 'bg-blue-50 text-blue-800 border-blue-300',
            self::LodgeVoted => 'bg-amber-50 text-amber-800 border-amber-300',
            self::Disbursed => 'bg-emerald-50 text-emerald-800 border-emerald-300',
        };
    }
}
