<?php

namespace App\Domains\ClubAccounting\Enums;

enum LodgeOffice: string
{
    case WorshipfulMaster = 'wm';
    case SeniorWarden = 'sw';
    case JuniorWarden = 'jw';
    case Chaplain = 'chaplain';
    case Treasurer = 'treasurer';
    case Secretary = 'secretary';
    case DirectorOfCeremonies = 'dc';
    case Almoner = 'almoner';
    case CharitySteward = 'charity_steward';
    case SeniorDeacon = 'sd';
    case JuniorDeacon = 'jd';
    case AssistantDC = 'assistant_dc';
    case Organist = 'organist';
    case AssistantSecretary = 'assistant_sec';
    case InnerGuard = 'inner_guard';
    case Steward = 'steward';
    case Tyler = 'tyler';
    case IPM = 'ipm';
    case CommitteeMember = 'committee_member';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::WorshipfulMaster => 'Worshipful Master',
            self::SeniorWarden => 'Senior Warden',
            self::JuniorWarden => 'Junior Warden',
            self::Chaplain => 'Chaplain',
            self::Treasurer => 'Treasurer',
            self::Secretary => 'Secretary',
            self::DirectorOfCeremonies => 'Director of Ceremonies',
            self::Almoner => 'Almoner',
            self::CharitySteward => 'Charity Steward',
            self::SeniorDeacon => 'Senior Deacon',
            self::JuniorDeacon => 'Junior Deacon',
            self::AssistantDC => 'Assistant Director of Ceremonies',
            self::Organist => 'Organist',
            self::AssistantSecretary => 'Assistant Secretary',
            self::InnerGuard => 'Inner Guard',
            self::Steward => 'Steward',
            self::Tyler => 'Tyler',
            self::IPM => 'Immediate Past Master',
            self::CommitteeMember => 'Committee Member',
            self::Member => 'Member / Brethren',
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::CommitteeMember => 'COMM',
            default => strtoupper($this->value),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::WorshipfulMaster, self::IPM => 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60 font-extrabold',
            self::SeniorWarden, self::JuniorWarden => 'bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 border-blue-200 dark:border-blue-800/60 font-bold',
            self::Secretary, self::Treasurer => 'bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 border-blue-200 dark:border-blue-800/60 font-bold',
            self::Almoner, self::CharitySteward => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-900 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60 font-bold',
            self::CommitteeMember => 'bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 border-blue-200 dark:border-blue-800/60 font-bold',
            self::Member => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800',
            default => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
        };
    }

    public function isProgressive(): bool
    {
        return in_array($this, [
            self::Steward,
            self::InnerGuard,
            self::JuniorDeacon,
            self::SeniorDeacon,
            self::JuniorWarden,
            self::SeniorWarden,
            self::WorshipfulMaster,
        ]);
    }

    public function isAdministrative(): bool
    {
        return ! $this->isProgressive() && $this !== self::Member && $this !== self::IPM && $this !== self::CommitteeMember;
    }

    public function category(): string
    {
        if ($this->isProgressive()) {
            return 'progressive';
        }

        if ($this->isAdministrative()) {
            return 'administrative';
        }

        if ($this === self::CommitteeMember) {
            return 'committee';
        }

        return 'other';
    }
}
