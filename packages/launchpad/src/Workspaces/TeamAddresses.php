<?php

namespace Launchpad\Workspaces;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Launchpad\Models\Setting;

/**
 * Where a team's pages live: /w/{slug}/... (the default) or {slug}.<domain>/... once staff turn subdomains on in HQ (Team addresses), for
 * every team at once. The same routes are registered either way, with the same names and the same {slug} parameter, so route() makes the
 * right address and nothing reading the slug changes. The rest of the site still answers on any host, sharing the session cookie, so its
 * links work from a team's subdomain too. Needs config launchpad.workspaces.subdomain_domain, a session cookie for that domain and its
 * subdomains (SESSION_DOMAIN), and wildcard DNS; HQ refuses to turn it on until all three are there.
 * shortcut: the mode is read when routes are registered, so turning it on or off clears the route cache (HQ does).
 */
final class TeamAddresses
{
    /** Never a team's address: a subdomain the site or its mail may need, or one that would mislead */
    public const RESERVED = ['www', 'app', 'api', 'hq', 'admin', 'mail', 'email', 'smtp', 'imap', 'ftp', 'static', 'assets', 'cdn', 'status', 'help',
        'docs', 'blog', 'support', 'billing', 'auth', 'login', 'sso', 'scim', 'mcp', 'w', 'staging', 'dev', 'test', 'demo', 'security'];

    public static function domain(): ?string
    {
        return ($domain = strtolower(trim((string) config('launchpad.workspaces.subdomain_domain'), " .\t"))) !== '' ? $domain : null;
    }

    /** On when staff turned it on in HQ (or config launchpad.workspaces.subdomains forces it) and there is a domain */
    public static function enabled(): bool
    {
        return self::domain() !== null && (bool) (config('launchpad.workspaces.subdomains') ?? Setting::get('team_subdomains', false));
    }

    /** Registers a team's routes under its subdomain or its /w/{slug} path */
    public static function group(Closure $routes): void
    {
        // an app with pages of its own has none of Launchpad's team pages (launchpad.workspaces.pages)
        if (! config('launchpad.workspaces.pages', true)) {
            return;
        }

        self::enabled() ? Route::domain('{slug}.'.self::domain())->group($routes) : Route::prefix('w/{slug}')->group($routes);
    }

    /** The start of a team's address with {slug} to fill in, for the Vue pages: '/w/{slug}' or 'https://{slug}.example.com' */
    public static function template(): string
    {
        return self::enabled() ? (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https').'://{slug}.'.self::domain() : '/w/{slug}';
    }

    /** A team's address, with $path after it */
    public static function url(string $slug, string $path = ''): string
    {
        return str_replace('{slug}', $slug, self::template()).($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED, true);
    }

    /** @return list<string> why subdomains cannot be turned on here yet (empty: they can) */
    public static function problems(): array
    {
        $domain = self::domain();

        if ($domain === null) {
            return ['Set LAUNCHPAD_TEAM_DOMAIN (config launchpad.workspaces.subdomain_domain) to the domain teams go under, say example.com.'];
        }

        $problems = [];
        $cookie = ltrim((string) config('session.domain'), '.');

        if ($cookie === '' || ($cookie !== $domain && ! Str::endsWith($domain, '.'.$cookie))) {
            $problems[] = "Set SESSION_DOMAIN=.{$domain}, so people stay signed in on every team's address.";
        }

        // a name nobody made: it resolves only with a wildcard record (*.domain)
        $probe = 'lp-check-'.Str::lower(Str::random(10)).'.'.$domain;
        if (gethostbyname($probe) === $probe) {
            $problems[] = "Add a wildcard DNS record, *.{$domain}, pointing at this site (and a certificate that covers it).";
        }

        return $problems;
    }
}
