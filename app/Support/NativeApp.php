<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The iOS and Android app (Capacitor, `mobile/`) is the member area in a web view. It adds
 * `ClubManagerApp` to its user agent, and opens `clubmanager://open?path=/…` links in itself.
 */
class NativeApp
{
    public const USER_AGENT_MARK = 'ClubManagerApp';

    public const SCHEME = 'clubmanager';

    public static function isApp(Request $request): bool
    {
        return str_contains((string) $request->userAgent(), self::USER_AGENT_MARK);
    }

    /**
     * Where Stripe or PayPal sends the payer back to. The app opens their pages in the phone's
     * browser, which has no session of ours, so from the app it is a page that hands the
     * address back to the app instead.
     */
    public static function returnUrl(Request $request, string $url): string
    {
        if (! self::isApp($request)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return route('app.return', ['to' => $path]);
    }

    /**
     * A same-site path to open in the app, or null for anything else (another site, `//host`).
     */
    public static function safePath(mixed $path): ?string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            ? $path
            : null;
    }

    public static function deepLink(string $path): string
    {
        return self::SCHEME.'://open?path='.rawurlencode($path);
    }
}
