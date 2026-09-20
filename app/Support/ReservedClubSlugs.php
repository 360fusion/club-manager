<?php

namespace App\Support;

/**
 * Club slugs live at the top level of the site (`/{club}`, `/{club}/admin`), so
 * they must never collide with a fixed top-level path.
 */
class ReservedClubSlugs
{
    /**
     * @var list<string>
     */
    public const WORDS = [
        'about', 'account', 'admin', 'api', 'assets', 'auth', 'blog', 'build', 'calendar', 'clubs',
        'committee', 'contact', 'css', 'dashboard', 'directory', 'docs', 'documents', 'dues', 'events',
        'favicon', 'fonts', 'forgot-password', 'help', 'home', 'images', 'index', 'js', 'livewire',
        'login', 'logout', 'meetings', 'members', 'new', 'news', 'notifications', 'portal', 'pricing',
        'privacy', 'profile', 'register', 'reset-password', 'robots', 'sanctum', 'search', 'security',
        'settings', 'site', 'storage', 'subscriptions', 'summons', 'superadmin', 'support', 'terms',
        'two-factor', 'ui-kit', 'up', 'vendor', 'webhooks',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::WORDS, true);
    }

    /**
     * The route constraint for a club slug segment: a normal slug that is not a
     * reserved word. Applied to the {slug} and {clubSlug} route parameters.
     */
    public static function routeRegex(): string
    {
        $words = implode('|', array_map('preg_quote', self::WORDS));

        return '(?!(?:'.$words.')(?:/|$))[A-Za-z0-9][A-Za-z0-9_-]*';
    }
}
