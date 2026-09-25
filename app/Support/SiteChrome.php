<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Page;

/**
 * Everything a public page needs to draw a club's own header, footer and theme around it: the club's details, the
 * site settings, and the menus. Shared by the website's pages and the other public pages (such as an event), so they
 * all look like the same site.
 */
class SiteChrome
{
    /**
     * @return array{club: array<string, mixed>, site: array<string, mixed>, navigation: mixed, footerNavigation: mixed}
     */
    public static function forClub(Club $club): array
    {
        $club->loadMissing('clubType');

        // Navigation links (All published pages marked show_in_navigation)
        $navigationPages = Page::where('club_id', $club->id)
            ->live()
            ->where('show_in_navigation', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'is_homepage']);

        $footerPages = Page::where('club_id', $club->id)
            ->live()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'is_homepage']);

        return [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'type_name' => $club->clubType->name,
                'tagline' => $club->settings['tagline'] ?? '',
                'primary_color' => $club->settings['primary_color'] ?? '#0369a1',
                'contact_email' => $club->settings['contact_email'] ?? $club->email,
                'meeting_formula' => $club->settings['meeting_formula'] ?? '',
                'address' => $club->settings['address'] ?? '',
                'logo_url' => $club->logo_url,
                'website_theme' => $club->settings['website_theme'] ?? SiteThemes::DEFAULT,
            ],
            'site' => [
                'meta_description' => $club->settings['seo_meta_description'] ?? null,
                'title_suffix' => $club->settings['seo_title_suffix'] ?? ('| '.$club->name),
                'footer_about_text' => $club->settings['footer_about_text'] ?? '',
                'footer_copyright_holder' => FooterCopyright::parts($club)['holder'],
                'footer_copyright_text' => FooterCopyright::parts($club)['text'],
                'header_layout' => $club->settings['header_layout'] ?? 'logo_left',
                'header_show_logo' => $club->settings['header_show_logo'] ?? true,
                'header_show_tagline' => $club->settings['header_show_tagline'] ?? true,
                'header_cta_enabled' => $club->settings['header_cta_enabled'] ?? false,
                'header_cta_text' => $club->settings['header_cta_text'] ?? '',
                'header_cta_link' => $club->settings['header_cta_link'] ?? '',
                'header_show_account_links' => $club->settings['header_show_account_links'] ?? true,
                'footer_layout' => $club->settings['footer_layout'] ?? 'simple',
                'footer_show_social' => $club->settings['footer_show_social'] ?? true,
                'footer_show_nav' => $club->settings['footer_show_nav'] ?? false,
                'social_facebook' => $club->settings['social_facebook'] ?? '',
                'social_instagram' => $club->settings['social_instagram'] ?? '',
                'social_twitter' => $club->settings['social_twitter'] ?? '',
                'social_youtube' => $club->settings['social_youtube'] ?? '',
                'social_linkedin' => $club->settings['social_linkedin'] ?? '',
                'social_tiktok' => $club->settings['social_tiktok'] ?? '',
                'social_whatsapp' => $club->settings['social_whatsapp'] ?? '',
                'footer_show_custom_columns' => $club->settings['footer_show_custom_columns'] ?? true,
                'footer_nav_page_ids' => $club->settings['footer_nav_page_ids'] ?? null,
                'footer_link_columns' => $club->settings['footer_link_columns'] ?? [],
                'font_pairing' => $club->settings['font_pairing'] ?? 'theme',
                'corner_style' => $club->settings['corner_style'] ?? 'theme',
                'custom_color_schemes' => SiteThemes::customSchemes($club),
                'announcement' => SiteAnnouncement::forClub($club),
                'tracking' => SiteTracking::forClub($club),
            ],
            'navigation' => $navigationPages,
            'footerNavigation' => $footerPages,
        ];
    }
}
