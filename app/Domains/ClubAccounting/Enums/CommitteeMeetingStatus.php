<?php

namespace App\Domains\ClubAccounting\Enums;

enum CommitteeMeetingStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case DraftSaved = 'draft_saved';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::DraftSaved => 'Draft Saved',
            self::Finalized => 'Finalized & Confirmed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-sky-100 text-sky-800 border-sky-200',
            self::InProgress => 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse',
            self::DraftSaved => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::Finalized => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        };
    }
}
