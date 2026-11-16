<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Launchpad\Models\SsoConnection;
use Launchpad\Sso\Scim;
use Symfony\Component\HttpFoundation\Response;

/** The bearer token of a team's SCIM provisioning: it finds the team's connection, which must have a verified domain */
class AuthenticateScim
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $connection = $token !== '' ? SsoConnection::where('scim_token_hash', hash('sha256', $token))->whereNotNull('domain_verified_at')->with('workspace')->first() : null;

        if ($connection === null || $connection->workspace->suspended_at !== null) {
            return Scim::error(401, 'A valid provisioning token is needed.')->header('WWW-Authenticate', 'Bearer');
        }

        $request->attributes->set('launchpad.scim', $connection);

        return $next($request);
    }
}
