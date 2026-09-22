<?php

namespace App\Enums;

enum SignatureRequestStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting signature',
            self::Signed => 'Signed',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
