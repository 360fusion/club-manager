<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a route as public on purpose. It does nothing; it exists so that "every route is classified" (see Testing\AssertsRoutesAreClassified)
 * can tell a deliberately open route from one somebody forgot to protect.
 */
class PublicRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
