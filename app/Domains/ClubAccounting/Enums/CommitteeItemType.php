<?php

namespace App\Domains\ClubAccounting\Enums;

enum CommitteeItemType: string
{
    case General = 'general';
    case CandidateVetting = 'candidate_vetting';
    case AccountsAudit = 'accounts_audit';
    case HallAffairs = 'hall_affairs';
    case Motion = 'motion';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General Business',
            self::CandidateVetting => 'Candidate Vetting (Rule 159)',
            self::AccountsAudit => 'Accounts & Bill Audit (Rule 158)',
            self::HallAffairs => 'Hall Affairs & Tenancy',
            self::Motion => 'Notice of Motion (Rule 160)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => '📋',
            self::CandidateVetting => '👤',
            self::AccountsAudit => '🔍',
            self::HallAffairs => '🏛️',
            self::Motion => '📜',
        };
    }
}
