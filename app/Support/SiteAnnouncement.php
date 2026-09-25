<?php

namespace App\Support;

use App\Models\Club;
use Carbon\CarbonImmutable;

/**
 * The bar across the top of a club's public site ("Installation on 12 April"). The club switches it on and
 * may give it a first and last day; outside that window nothing is sent to the page at all.
 */
class SiteAnnouncement
{
    /**
     * @var list<string>
     */
    public const STYLES = ['info', 'success', 'warning', 'dark'];

    /**
     * @return array{text: string, link_label: string, link_url: string, style: string, dismissible: bool, key: string}|null
     */
    public static function forClub(Club $club, ?CarbonImmutable $now = null): ?array
    {
        $settings = $club->settings ?? [];
        $text = trim((string) ($settings['announcement_text'] ?? ''));

        if (empty($settings['announcement_enabled']) || $text === '') {
            return null;
        }

        $today = ($now ?? CarbonImmutable::now())->startOfDay();
        $starts = self::day($settings['announcement_starts_on'] ?? null);
        $ends = self::day($settings['announcement_ends_on'] ?? null);

        if (($starts && $today->lt($starts)) || ($ends && $today->gt($ends))) {
            return null;
        }

        $link = trim((string) ($settings['announcement_link_url'] ?? ''));
        $style = $settings['announcement_style'] ?? 'info';

        return [
            'text' => $text,
            'link_label' => trim((string) ($settings['announcement_link_label'] ?? '')),
            'link_url' => $link,
            'style' => in_array($style, self::STYLES, true) ? $style : 'info',
            'dismissible' => (bool) ($settings['announcement_dismissible'] ?? true),
            // A visitor who closes the bar sees it again once its wording changes.
            'key' => substr(md5($text.'|'.$link), 0, 12),
        ];
    }

    private static function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
