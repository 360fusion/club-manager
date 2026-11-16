<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Launchpad\Support\Locales;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of this request: the signed-in person's saved one, else the one chosen in this browser's session, else what the browser
 * asks for, else the app's own. Only a language listed in config launchpad.locales is used.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $listed = Locales::supported();

        $locale = collect([$request->user()?->locale, $request->hasSession() ? $request->session()->get('launchpad.locale') : null])
            ->first(fn ($code) => is_string($code) && isset($listed[$code]))
            ?? Locales::fromHeader($request->header('Accept-Language'))
            ?? Locales::default();

        Locales::use($locale);

        return $next($request);
    }
}
