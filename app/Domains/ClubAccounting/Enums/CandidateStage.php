<?php

namespace App\Domains\ClubAccounting\Enums;

enum CandidateStage: string
{
    case Enquiry = 'enquiry';
    case InterviewPending = 'interview_pending';
    case LodgeCommittee = 'lodge_committee';
    case BallotApproved = 'ballot_approved';
    case Initiated = 'initiated';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Initial Enquiry',
            self::InterviewPending => 'Interview Pending',
            self::LodgeCommittee => 'Lodge Committee (Rule 159)',
            self::BallotApproved => 'Ballot Approved',
            self::Initiated => 'Initiated Bro',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Enquiry => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-slate-300 dark:border-slate-700',
            self::InterviewPending => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
            self::LodgeCommittee => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::BallotApproved => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
            self::Initiated => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::Rejected => 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60',
            self::Withdrawn => 'bg-gray-100 text-gray-600 border-gray-300',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Enquiry => 1,
            self::InterviewPending => 2,
            self::LodgeCommittee => 3,
            self::BallotApproved => 4,
            self::Initiated => 5,
            self::Rejected => 6,
            self::Withdrawn => 7,
        };
    }
}
