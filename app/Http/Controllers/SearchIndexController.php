<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Page;
use App\Support\SiteSeo;
use Illuminate\Http\Response;

/**
 * A sitemap of the club websites, and each club's own sitemap. (robots.txt is a plain file in public/,
 * because a server that returns 404 for a missing static robots.txt would hide a generated one.)
 */
class SearchIndexController extends Controller
{
    private const MAX_URLS = 5000;

    /**
     * One entry per club website that has not asked to be left out of search engines.
     */
    public function index(): Response
    {
        $entries = [];

        Club::query()->orderBy('id')->select(['id', 'slug', 'settings', 'updated_at'])->lazy()->each(function (Club $club) use (&$entries) {
            if (! SiteSeo::siteHidden($club) && count($entries) < self::MAX_URLS) {
                $entries[] = ['loc' => route('public.site.sitemap', ['clubSlug' => $club->slug])];
            }
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= '<sitemap><loc>'.e($entry['loc']).'</loc></sitemap>'."\n";
        }

        return $this->xml($xml.'</sitemapindex>');
    }

    /**
     * A club's published pages (never members-only or hidden ones) and its public events.
     */
    public function club(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        abort_if(SiteSeo::siteHidden($club), 404);

        $urls = [];

        Page::where('club_id', $club->id)->live()->orderBy('sort_order')->get()
            ->filter(fn (Page $page) => SiteSeo::pageIndexable($club, $page))
            ->each(function (Page $page) use ($club, &$urls) {
                $urls[] = ['loc' => SiteSeo::pageUrl($club, $page), 'lastmod' => $page->updated_at, 'priority' => $page->is_homepage ? '1.0' : '0.7'];
            });

        $club->events()->published()->where('status', '!=', 'cancelled')->visibleTo(null)
            ->where('starts_at', '>=', now()->subDays(30))->orderBy('starts_at')->limit(self::MAX_URLS - count($urls))->get()
            ->each(function ($event) use ($club, &$urls) {
                $urls[] = ['loc' => route('public.event', ['clubSlug' => $club->slug, 'eventSlug' => $event->slug]), 'lastmod' => $event->updated_at, 'priority' => '0.5'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url['loc']).'</loc>'
                .($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>' : '')
                .'<priority>'.$url['priority'].'</priority></url>'."\n";
        }

        return $this->xml($xml.'</urlset>');
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
