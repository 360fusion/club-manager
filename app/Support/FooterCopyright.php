<?php

namespace App\Support;

use App\Models\Club;

/**
 * The line at the very bottom of a club's public site: "© 2026 The Lodge of Fraternity. All rights reserved."
 *
 * The © symbol and the year are never stored. The club only edits who holds the copyright and any wording that
 * follows, and the year is filled in whenever the page is shown, so it is always the current one.
 */
class FooterCopyright
{
    public const DEFAULT_TEXT = 'All rights reserved.';

    /**
     * What the club has chosen for the holder and the wording after it.
     *
     * Clubs that saved the whole line as one piece of text before this was split (with the year typed into it) are
     * read back into the two parts: the © and year are dropped, and a line that starts with the club's own name
     * keeps whatever follows the name as its wording.
     *
     * @return array{holder: string, text: string}
     */
    public static function parts(Club $club): array
    {
        $settings = $club->settings ?? [];

        if (array_key_exists('footer_copyright_holder', $settings) || array_key_exists('footer_copyright_text', $settings)) {
            return [
                'holder' => trim((string) ($settings['footer_copyright_holder'] ?? '')) ?: $club->name,
                'text' => array_key_exists('footer_copyright_text', $settings) ? trim((string) $settings['footer_copyright_text']) : self::DEFAULT_TEXT,
            ];
        }

        $legacy = trim((string) ($settings['footer_copyright'] ?? ''));
        $rest = trim((string) preg_replace('/^\s*(?:©|\(c\)|copyright(?:\s*©)?)?\s*(?:\d{4}(?:\s*[-–]\s*\d{4})?)?\s*/iu', '', $legacy));

        if ($rest === '') {
            return ['holder' => $club->name, 'text' => self::DEFAULT_TEXT];
        }

        if (stripos($rest, $club->name) === 0) {
            return ['holder' => $club->name, 'text' => trim(ltrim(substr($rest, strlen($club->name)), ' .,;-–'))];
        }

        return ['holder' => rtrim($rest, '. '), 'text' => ''];
    }

    /**
     * The finished line for a year (this year unless told otherwise).
     */
    public static function line(Club $club, ?int $year = null): string
    {
        ['holder' => $holder, 'text' => $text] = self::parts($club);

        return trim('© '.($year ?? (int) date('Y')).' '.rtrim($holder, '.').'.'.($text !== '' ? ' '.$text : ''));
    }
}
