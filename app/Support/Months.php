<?php

namespace App\Support;

/**
 * Month names and numbers, for the places that store a month as a name ("October") and the
 * places that store it as a number.
 */
class Months
{
    public const NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /**
     * The month number for a name or a three letter abbreviation, or null when it is neither.
     */
    public static function number(?string $name): ?int
    {
        $name = strtolower(trim((string) $name));

        if (strlen($name) < 3) {
            return null;
        }

        if ($name === 'sept') {
            return 9;
        }

        foreach (self::NAMES as $number => $full) {
            if (str_starts_with(strtolower($full), $name)) {
                return $number;
            }
        }

        return null;
    }

    public static function name(?int $number): ?string
    {
        return self::NAMES[$number] ?? null;
    }
}
