<?php

namespace App\Domains\ClubAccounting\Enums;

enum AttendanceType: string
{
    case Present = 'present';
    case Apology = 'apology';
    case RemoteLink = 'remote_link';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present in Person',
            self::Apology => 'Apology for Absence',
            self::RemoteLink => 'Attending via Video Link',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Present => '✅',
            self::Apology => '✉️',
            self::RemoteLink => '💻',
        };
    }
}
