<?php

namespace App\Domains\ClubAccounting\Enums;

enum CommitteeMeetingStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case DraftSaved = 'draft_saved';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft, self::DraftSaved => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::Finalized => 'Finalized & Confirmed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft, self::DraftSaved => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60',
            self::Scheduled => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
            self::InProgress => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60 animate-pulse',
            self::Finalized => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60',
        };
    }
}
