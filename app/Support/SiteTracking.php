<?php

namespace App\Support;

use App\Models\Club;

/**
 * Visitor statistics and the cookie notice for a club's public site.
 *
 * Plausible counts visits without cookies, so it needs no consent. Google Analytics does set cookies, so the
 * page only loads it once the visitor accepts (which is why choosing Google always brings up the notice).
 */
class SiteTracking
{
    public const PROVIDERS = ['none', 'google', 'plausible'];

    /**
     * A Google Analytics 4 measurement ID, or the domain a Plausible site is registered under.
     */
    public const GOOGLE_ID = '/^G-[A-Z0-9]{4,20}$/';

    public const PLAUSIBLE_DOMAIN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

    /**
     * @return array{provider: string, id: string, banner: bool, banner_text: string, banner_link_label: string, banner_link_url: string}
     */
    public static function forClub(Club $club): array
    {
        $settings = $club->settings ?? [];
        $provider = $settings['analytics_provider'] ?? 'none';
        $id = trim((string) ($settings['analytics_id'] ?? ''));

        $valid = match ($provider) {
            'google' => (bool) preg_match(self::GOOGLE_ID, $id),
            'plausible' => (bool) preg_match(self::PLAUSIBLE_DOMAIN, $id),
            default => false,
        };

        if (! $valid) {
            $provider = 'none';
            $id = '';
        }

        return [
            'provider' => $provider,
            'id' => $id,
            // Google's cookies need the visitor's say-so, so it always shows the notice.
            'banner' => $provider === 'google' || ! empty($settings['cookie_banner_enabled']),
            'banner_text' => trim((string) ($settings['cookie_banner_text'] ?? '')),
            'banner_link_label' => trim((string) ($settings['cookie_banner_link_label'] ?? '')),
            'banner_link_url' => trim((string) ($settings['cookie_banner_link_url'] ?? '')),
        ];
    }
}
