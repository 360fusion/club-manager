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
            self::Member => 'Member / Brethren',
        };
    }

    public function shortCode(): string
    {
        return strtoupper($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::WorshipfulMaster => 'bg-amber-100 text-amber-900 border-amber-300 font-extrabold',
            self::SeniorWarden, self::JuniorWarden => 'bg-indigo-100 text-indigo-900 border-indigo-200 font-bold',
            self::Secretary, self::Treasurer => 'bg-purple-100 text-purple-900 border-purple-200 font-bold',
            self::Almoner, self::CharitySteward => 'bg-emerald-100 text-emerald-900 border-emerald-200 font-bold',
            self::Member => 'bg-slate-100 text-slate-600 border-slate-200',
            default => 'bg-blue-50 text-blue-800 border-blue-200',
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
}
