<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `launchpad.json`: an API client that forgot `Accept: application/json` still gets JSON errors (validation, 404, 429), never a web page */
class ForceJsonResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
