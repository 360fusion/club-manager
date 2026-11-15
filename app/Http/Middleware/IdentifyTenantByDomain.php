<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\SiteRelease;
use App\Services\StaticSite\StaticSiteBuilder;
use App\Support\ClubDomain;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a lodge's website on its own verified domain: `lodge.org/about` is answered by the `/site/{club}/about`
 * route, so every page, article, event and form works there without a second set of routes. On the main address,
 * a lodge that chose to can have `/site/{club}/...` forward to its domain. Runs before routing (a global middleware).
 */
class IdentifyTenantByDomain
{
    /**
     * Paths on a lodge's domain that belong to the main app (signing in, the members area), sent to the main address,
     * where the session lives. A lodge page with one of these slugs is only reachable at /site/{club}.
     */
    private const APP_PATHS = [
        'login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor-challenge', 'email', 'user',
        'members', 'superadmin', 'sanctum', 'api', 'webhooks', 'internal', 'up', '_boost', '_inertia',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($host === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
            $this->markStaticRender($request);

            return $this->forwardToDomain($request) ?? $next($request);
        }

        $club = Club::where('custom_domain', $host)->where('domain_status', 'active')->first();

        if (! $club) {
            // Any other Host header is whatever the sender typed. Links in emails (password reset, verification, booking
            // links) must never be built from it, so they are made from the app's own address instead.
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));

            return $this->forwardToDomain($request) ?? $next($request);
        }

        $path = $request->getPathInfo();
        $first = explode('/', trim($path, '/'))[0];

        if (in_array($first, self::APP_PATHS, true) || str_starts_with($first, 'livewire')) {
            return new RedirectResponse(rtrim((string) config('app.url'), '/').$request->getRequestUri());
        }

        if ($static = $this->staticFile($request, $club)) {
            return $static;
        }

        // The session cookie must belong to this host, or forms on the lodge's domain fail their CSRF check.
        config(['session.domain' => null]);

        $prefix = '/site/'.$club->slug;
        $query = $request->server('QUERY_STRING') ? '?'.$request->server('QUERY_STRING') : '';

        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            // Links built for the main address still work here; a page visit lands on the clean address, while
            // forms keep posting to their /site/{club} route.
            // Leading slashes are collapsed so "//other.example" cannot become a protocol-relative redirect off this domain.
            return $request->isMethodSafe()
                ? new RedirectResponse('/'.ltrim(substr($path, strlen($prefix)), '/\\').$query, 301)
                : $next($request);
        }

        $server = $request->server->all();
        $server['REQUEST_URI'] = $prefix.($path === '/' ? '' : $path).$query;

        $rewritten = $request->duplicate(server: $server);
        $rewritten->attributes->set('tenant_club', $club);
        // What the visitor actually asked for, so Inertia puts that address (not /site/{club}/...) in the browser.
        $rewritten->attributes->set('club_domain_uri', $request->getRequestUri());

        return $next($rewritten);
    }

    /** What a static website file is sent as; anything else in a release is never served. */
    private const STATIC_TYPES = [
        'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json', 'xml' => 'application/xml; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'pdf' => 'application/pdf',
    ];

    /**
     * A lodge that serves its published static website (settings.static_site_serve) gets each page and file from the
     * live version's folder, without running the app. Addresses it doesn't have (events, bookings, forms, members-only
     * pages) and previews fall through to the app as usual.
     *
     * Once Caddy is set up (REMINDERS.md) it answers these from storage/app/sites/hosts/{domain} before PHP runs; this
     * stays as the fallback for servers that don't (Forge today) and keeps the same rules.
     */
    private function staticFile(Request $request, Club $club): ?Response
    {
        if (! $request->isMethodSafe() || empty($club->settings['static_site_serve'])
            || $request->query->has('preview') || $request->query->has('preview_theme')) {
            return null;
        }

        $root = realpath(SiteRelease::currentPath($club->id));
        $path = rawurldecode($request->getPathInfo());

        if (! $root || str_contains($path, '..') || preg_match('#/\.#', $path)) {
            return null;
        }

        foreach ([$path, rtrim($path, '/').'/index.html'] as $candidate) {
            $file = realpath($root.'/'.ltrim($candidate, '/'));
            $type = self::STATIC_TYPES[strtolower(pathinfo((string) $file, PATHINFO_EXTENSION))] ?? null;

            if ($file && $type && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
                return new BinaryFileResponse($file, 200, [
                    'Content-Type' => $type,
                    // Built files have hashed names and never change; pages and images are checked again each visit.
                    'Cache-Control' => str_contains($candidate, '/build/') ? 'public, max-age=31536000, immutable' : 'public, max-age=0, must-revalidate',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        return null;
    }

    /**
     * Headless Chrome opening a page for the static website build (App\Services\StaticSite\StaticSiteBuilder) comes
     * with a signed `?static` link: the page is then drawn exactly as it is for the static files, with its static address.
     */
    private function markStaticRender(Request $request): void
    {
        if (! $request->query->has('static') || ! preg_match('#^/site/([^/]+)(/.*)?$#', $request->getPathInfo(), $match)
            || ! URL::hasValidSignature($request)) {
            return;
        }

        $club = Club::where('slug', $match[1])->first();

        if ($club) {
            $request->attributes->set('club_domain_uri', $match[2] ?? '/');
            $request->attributes->set('static_site', StaticSiteBuilder::flags($club));
        }
    }

    /**
     * On the main address, a page visit to /site/{club}/... of a lodge that sends visitors to its live domain.
     * Theme and draft previews, and members-only pages sent here to sign in (`?members`), stay here, because they need the session here.
     */
    private function forwardToDomain(Request $request): ?Response
    {
        if (! $request->isMethodSafe() || $request->attributes->has('static_site') || $request->query->has('preview') || $request->query->has('preview_theme') || $request->query->has('members')
            || ! preg_match('#^/site/([^/]+)(/.*)?$#', $request->getPathInfo(), $match)) {
            return null;
        }

        $club = Club::where('slug', $match[1])->first();

        if (! $club || ! ClubDomain::isLive($club) || empty($club->settings['site_redirect_to_domain'])) {
            return null;
        }

        $query = $request->server('QUERY_STRING') ? '?'.$request->server('QUERY_STRING') : '';

        return new RedirectResponse('https://'.$club->custom_domain.($match[2] ?? '/').$query, 301);
    }
}
