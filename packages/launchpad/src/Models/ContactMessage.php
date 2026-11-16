<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /** A reference the sender can quote: CM-, then eight letters and digits */
    public static function newReference(): string
    {
        do {
            $reference = 'CM-'.Str::upper(Str::random(8));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }
}
