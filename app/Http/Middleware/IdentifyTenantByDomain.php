<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Support\ClubDomain;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            return $this->forwardToDomain($request) ?? $next($request);
        }

        $club = Club::where('custom_domain', $host)->where('domain_status', 'active')->first();

        if (! $club) {
            return $this->forwardToDomain($request) ?? $next($request);
        }

        $path = $request->getPathInfo();
        $first = explode('/', trim($path, '/'))[0];

        if (in_array($first, self::APP_PATHS, true) || str_starts_with($first, 'livewire')) {
            return new RedirectResponse(rtrim((string) config('app.url'), '/').$request->getRequestUri());
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

    /**
     * On the main address, a page visit to /site/{club}/... of a lodge that sends visitors to its live domain.
     * Theme and draft previews stay here, because they need the admin's session.
     */
    private function forwardToDomain(Request $request): ?Response
    {
        if (! $request->isMethodSafe() || $request->query->has('preview') || $request->query->has('preview_theme')
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
