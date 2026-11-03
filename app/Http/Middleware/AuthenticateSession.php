<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's session check (other sessions end once the password changes), skipped when the request was signed in some
 * other way, such as an API token, where there is no session to check and the guard cannot answer it.
 */
class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next): Response
    {
        if (! $this->auth->guard() instanceof SessionGuard) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
