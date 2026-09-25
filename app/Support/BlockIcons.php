<?php

namespace App\Support;

/**
 * The icons the website builder's blocks can show (feature cards, stats). A block stores only the key, and
 * BlockNormaliser drops anything not listed here, so no markup can be smuggled in through an icon field.
 *
 * Keep in step with resources/js/Support/icons.js, which holds the drawings.
 */
class BlockIcons
{
    public const NAMES = [
        'users',
        'user-group',
        'people-group',
        'heart',
        'hand-holding-heart',
        'hands-holding-circle',
        'handshake',
        'hands',
        'hand-holding-dollar',
        'gift',
        'seedling',
        'leaf',
        'graduation-cap',
        'book-open',
        'book',
        'landmark',
        'building-columns',
        'church',
        'house',
        'scale-balanced',
        'gavel',
        'compass-drafting',
        'hammer',
        'award',
        'medal',
        'trophy',
        'star',
        'crown',
        'shield-halved',
        'key',
        'lightbulb',
        'fire',
        'sun',
        'moon',
        'calendar-check',
        'calendar-days',
        'clock',
        'location-dot',
        'map',
        'globe',
        'envelope',
        'phone',
        'comments',
        'newspaper',
        'camera',
        'utensils',
        'wine-glass',
        'music',
        'futbol',
        'anchor',
        'ship',
        'peace',
        'dove',
        'infinity',
        'circle-check',
        'check',
        'arrow-right',
    ];

    public static function clean(mixed $value): string
    {
        return is_string($value) && in_array($value, self::NAMES, true) ? $value : '';
    }
}
