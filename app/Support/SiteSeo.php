<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Event;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * What goes in the <head> of a club's public page: title, description, canonical address, the picture and
 * text used when the link is shared, the robots hint, the site icon and structured data for search engines.
 *
 * It is written into the HTML on the server (see app.blade.php) because the site is drawn in the browser:
 * Facebook, WhatsApp and Messages never run that code, so tags added by the page itself would not be seen.
 */
class SiteSeo
{
    /**
     * Whether the club has asked search engines to leave its whole site alone.
     */
    public static function siteHidden(Club $club): bool
    {
        return (bool) ($club->settings['noindex_site'] ?? false);
    }

    /**
     * Whether a page may be listed by search engines and in the sitemap. A members-only page is never
     * listed (its visitors are sent to log in), nor is an unpublished one.
     */
    public static function pageIndexable(Club $club, Page $page): bool
    {
        return $page->isLive() && ! $page->is_members_only && ! $page->noindex && ! self::siteHidden($club);
    }

    /**
     * An address a crawler can follow: an upload path like /storage/x.jpg becomes a full URL; anything that
     * is not a web address or a site path is dropped.
     */
    public static function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return preg_match('#^https?://#i', $url) ? $url : null;
    }

    /**
     * The page's public address (the homepage has none after the club).
     */
    public static function pageUrl(Club $club, Page $page): string
    {
        return $page->is_homepage
            ? route('public.site', ['clubSlug' => $club->slug])
            : route('public.site', ['clubSlug' => $club->slug, 'pageSlug' => $page->slug]);
    }

    /**
     * The title shown in the browser tab and by search engines (the same wording the page itself uses).
     */
    public static function title(Club $club, Page $page): string
    {
        $suffix = $club->settings['seo_title_suffix'] ?? ('| '.$club->name);

        return trim(($page->meta_title ?: $page->title).' '.($suffix !== '' ? $suffix : '- '.$club->name));
    }

    /**
     * @param  Collection<int, Event>  $events  upcoming public events, listed as structured data
     * @return array{title: string, description: string, canonical: string, robots: string|null, image: string|null, site_name: string, icon: string|null, json_ld: string}
     */
    public static function forPage(Club $club, Page $page, Request $request, Collection $events, bool $preview = false): array
    {
        $settings = $club->settings ?? [];
        $canonical = self::pageUrl($club, $page);
        $description = trim((string) ($page->meta_description ?: ($settings['seo_meta_description'] ?? '')));
        $image = self::absoluteUrl($page->share_image)
            ?? self::absoluteUrl($settings['share_image_url'] ?? null)
            ?? self::absoluteUrl($club->logo_url);

        // A theme preview or a private page preview is not a real page, so it never goes in a search engine.
        $indexable = self::pageIndexable($club, $page) && ! $request->has('preview_theme') && ! $preview;

        return [
            'title' => self::title($club, $page),
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $indexable ? null : 'noindex, nofollow',
            'image' => $image,
            'site_name' => $club->name,
            'icon' => self::absoluteUrl($settings['site_icon_url'] ?? null),
            'json_ld' => $indexable ? self::structuredData($club, $page, $events) : '',
        ];
    }

    /**
     * The club as an Organization (plus its next public events on a page that shows events), as a JSON string
     * that is safe to place inside a <script> tag.
     *
     * @param  Collection<int, Event>  $events
     */
    private static function structuredData(Club $club, Page $page, Collection $events): string
    {
        $settings = $club->settings ?? [];
        $home = route('public.site', ['clubSlug' => $club->slug]);

        $sameAs = collect(['social_facebook', 'social_instagram', 'social_twitter', 'social_youtube', 'social_linkedin', 'social_tiktok'])
            ->map(fn (string $key) => self::absoluteUrl($settings[$key] ?? null))
            ->filter()->values()->all();

        $organisation = array_filter([
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => $club->name,
            'url' => $home,
            'logo' => self::absoluteUrl($club->logo_url),
            'email' => $settings['contact_email'] ?? $club->email,
            'telephone' => $settings['phone'] ?? null,
            'address' => ! empty($settings['address']) ? ['@type' => 'PostalAddress', 'streetAddress' => (string) $settings['address']] : null,
            'sameAs' => $sameAs ?: null,
        ]);

        $graph = [$organisation];

        $hasEvents = collect($page->blocks ?? [])->contains(fn ($block) => in_array($block['type'] ?? null, ['calendar', 'events_calendar'], true));

        if ($hasEvents) {
            foreach ($events->take(10) as $event) {
                $graph[] = array_filter([
                    '@type' => 'Event',
                    'name' => $event->title,
                    'startDate' => $event->starts_at?->toIso8601String(),
                    'endDate' => $event->ends_at?->toIso8601String(),
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => $event->formatted_location ? ['@type' => 'Place', 'name' => $event->formatted_location] : null,
                    'url' => route('public.event', ['clubSlug' => $club->slug, 'eventSlug' => $event->slug]),
                    'organizer' => ['@id' => $home.'#organization'],
                ]);
            }
        }

        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
