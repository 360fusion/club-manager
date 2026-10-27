<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections: no MIME sniffing, no framing by other sites, a
 * restrained referrer, and HTTPS-only for a year once the site is served over TLS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Staging and local copies must never end up in a search engine, whatever robots.txt says.
        if (! app()->isProduction()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Report-only unless CSP_ENFORCE is on: browsers then log violations to the console without blocking anything.
        // shortcut: 'unsafe-inline' and 'unsafe-eval' stay because Alpine/Livewire's standard build needs them; move to nonces and the CSP build to drop them.
        $header = config('app.csp_enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';

        if (! app()->environment('local') && ! $response->headers->has($header)) {
            $response->headers->set($header, implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://plausible.io",
                "style-src 'self' 'unsafe-inline' https:",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https:",
                "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com https://plausible.io",
                "frame-src 'self' https://www.youtube-nocookie.com https://www.openstreetmap.org",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https://checkout.stripe.com https://www.paypal.com https://www.sandbox.paypal.com",
                "frame-ancestors 'self'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
