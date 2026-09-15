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
            self::Enquiry => 'bg-slate-100 text-slate-800 border-slate-300',
            self::InterviewPending => 'bg-blue-50 text-blue-800 border-blue-200',
            self::LodgeCommittee => 'bg-amber-50 text-amber-800 border-amber-300',
            self::BallotApproved => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::Initiated => 'bg-purple-50 text-purple-800 border-purple-300',
            self::Rejected => 'bg-rose-50 text-rose-800 border-rose-300',
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
