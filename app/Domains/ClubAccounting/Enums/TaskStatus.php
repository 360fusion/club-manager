<?php

namespace App\Domains\ClubAccounting\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 border-amber-200',
            self::InProgress => 'bg-sky-100 text-sky-800 border-sky-200',
            self::Completed => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        };
    }
}
