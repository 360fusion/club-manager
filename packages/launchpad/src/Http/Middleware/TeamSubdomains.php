<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * In the web group while team subdomains are on (Launchpad\Workspaces\TeamAddresses). The site's own links are made on its main address
 * (a team's are made on its subdomain by their route), while scripts and styles load from the host the page is on, so the browser does not
 * refuse them as cross-origin. A redirect to another host cannot be followed by an Inertia visit, so it is told to leave the page instead
 * (409 with X-Inertia-Location, Inertia's own way).
 */
class TeamSubdomains
{
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
        URL::useAssetOrigin($request->getSchemeAndHttpHost());

        $response = $next($request);

        if ($request->header('X-Inertia') && $response instanceof RedirectResponse && ($host = parse_url($response->getTargetUrl(), PHP_URL_HOST)) && $host !== $request->getHost()) {
            return response('', 409, ['X-Inertia-Location' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
