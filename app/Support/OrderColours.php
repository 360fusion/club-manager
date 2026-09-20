<?php

namespace App\Support;

/**
 * The colour each order (club type) is shown in: news and event date tiles, calendar
 * pills, club chips. The stored value is one of KEYS; the front end turns it into
 * classes, so the palette here and in resources/js/Utils/orderColour.js must match.
 */
final class OrderColours
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky',
        'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose', 'slate', 'stone',
    ];

    public const DEFAULT = 'slate';

    /**
     * Starting colours. Where an order's regalia has a well-known colour it follows that
     * (Craft blue, Royal Arch red, Rose Croix pink, Red Cross of Constantine purple,
     * Templar black, Allied Masonic Degrees green); the rest are chosen to stay apart.
     *
     * @var array<string, string>
     */
    private const BY_CODE = [
        'craft_lodge' => 'sky',
        'royal_arch' => 'red',
        'mark_lodge' => 'orange',
        'royal_ark_mariner' => 'teal',
        'rose_croix' => 'pink',
        'knights_templar' => 'slate',
        'secret_monitor' => 'amber',
        'red_cross_constantine' => 'purple',
        'allied_masonic' => 'emerald',
        'cryptic_council' => 'indigo',
        'ktp_tabernacle' => 'stone',
        'sria_college' => 'violet',
        'royal_order_scotland' => 'cyan',
        'scarlet_cord' => 'lime',
        'rowing' => 'blue',
        'rugby' => 'green',
    ];

    public static function for(string $code): string
    {
        return self::BY_CODE[$code] ?? self::DEFAULT;
    }

    public static function isValid(?string $colour): bool
    {
        return in_array($colour, self::KEYS, true);
    }
}
