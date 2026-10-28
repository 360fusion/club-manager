<?php

namespace App\Services;

use App\Models\Club;
use App\Support\SiteThemes;

/**
 * The standard navigation pages and website settings every club website starts with. Safe to call on every request:
 * it does nothing once a club has them.
 */
class DefaultSitePages
{
    public function ensure(Club $club): void
    {
        $settings = $club->settings ?? [];
        $settingsUpdated = false;

        if (empty($settings['website_theme'])) {
            $settings['website_theme'] = SiteThemes::DEFAULT;
            $settingsUpdated = true;
        }

        if (empty($settings['contact_email'])) {
            $settings['contact_email'] = $club->email;
            $settingsUpdated = true;
        }

        if (empty($settings['seo_title_suffix'])) {
            $settings['seo_title_suffix'] = ' - '.$club->name;
            $settingsUpdated = true;
        }

        if (! array_key_exists('header_layout', $settings)) {
            $settings['header_layout'] = 'logo_left';
            $settings['header_show_logo'] = true;
            $settings['header_show_tagline'] = true;
            $settings['header_cta_enabled'] = false;
            $settings['header_show_account_links'] = true;
            $settings['footer_layout'] = 'simple';
            $settings['footer_show_social'] = true;
            $settings['footer_show_nav'] = false;
            $settingsUpdated = true;
        }

        if ($settingsUpdated) {
            $club->settings = $settings;
            $club->save();
        }

        $defaultPages = [
            [
                'title' => 'Home',
                'slug' => 'home',
                'is_homepage' => true,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 1,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Welcome to '.$club->name,
                        'subtitle' => 'Official Website & Portal',
                        'cta_text' => 'Join Us',
                        'cta_link' => '/site/'.$club->slug.'/join-us',
                    ],
                    [
                        'type' => 'news_feed',
                        'heading' => 'Latest News & Updates',
                    ],
                    [
                        'type' => 'events_calendar',
                        'heading' => 'Upcoming events and meetings',
                    ],
                ],
            ],
            [
                'title' => 'About',
                'slug' => 'about',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 2,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'About '.$club->name,
                        'subtitle' => 'History, Values & Community',
                    ],
                    [
                        'type' => 'rich_text',
                        'heading' => 'Our History & Mission',
                        'content' => '<p>Welcome to '.e($club->name).'. Our organization is dedicated to fostering community, excellence, and fellowship.</p>',
                    ],
                ],
            ],
            [
                'title' => 'Join Us',
                'slug' => 'join-us',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 3,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Join '.$club->name,
                        'subtitle' => 'Become a Member Today',
                    ],
                    [
                        'type' => 'pricing_cards',
                        'heading' => 'Membership options and dues',
                    ],
                    [
                        'type' => 'rich_text',
                        'heading' => 'Application Process',
                        'content' => '<p>To enquire about membership, please reach out to our secretary or complete our visitor registration form.</p>',
                    ],
                ],
            ],
            [
                'title' => 'News',
                'slug' => 'news',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 4,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Club News & Updates',
                        'subtitle' => 'Latest Articles & Announcements',
                    ],
                    [
                        'type' => 'news_feed',
                        'heading' => 'Recent Articles',
                    ],
                ],
            ],
            [
                'title' => 'Contact',
                'slug' => 'contact',
                'is_homepage' => false,
                'is_published' => true,
                'show_in_navigation' => true,
                'sort_order' => 5,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'title' => 'Contact Us',
                        'subtitle' => 'Get in touch with our team',
                    ],
                    [
                        'type' => 'contact_details',
                        'eyebrow' => 'CONTACT',
                        'title' => 'Get in touch',
                        'description' => 'Whether you\'re interested in joining '.$club->name.', a visiting member, or simply want to learn more, we\'d be delighted to hear from you.',
                    ],
                    [
                        'type' => 'contact_form',
                        'heading' => 'Send us a message',
                        'subtitle' => 'Have questions or need assistance? Fill out the form below.',
                        'recipient_email' => $club->settings['contact_email'] ?? $club->email,
                        'cc_emails' => '',
                        'name_required' => true,
                        'email_required' => true,
                        'phone_required' => false,
                        'message_required' => true,
                        'button_text' => 'Send Message',
                        'success_message' => 'Thank you! Your message has been sent successfully.',
                    ],
                ],
            ],
        ];

        // Only ever seeded once: after that, a missing slug means an admin renamed or removed that page on
        // purpose, not that it needs putting back. Without this flag, renaming "About" away would leave a
        // fresh blank "About" reappearing every time the site is visited.
        if (! empty($settings['default_pages_seeded'])) {
            $home = $club->pages()->where('slug', 'home')->first();

            if ($home && $home->title !== 'Home') {
                $home->update(['title' => 'Home']);
            }

            return;
        }

        foreach ($defaultPages as $def) {
            if (! $club->pages()->where('slug', $def['slug'])->exists()) {
                $club->pages()->create($def);
            }
        }

        $club->update(['settings' => array_merge($club->settings ?? [], ['default_pages_seeded' => true])]);
    }
}
