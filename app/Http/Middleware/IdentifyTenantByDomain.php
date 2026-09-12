<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantByDomain
{
    /**
     * Handle an incoming request and map custom domain to club tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Exclude default app hostnames
        if (! in_array($host, ['localhost', '127.0.0.1', 'club-manager.test', 'clubmanager.com'], true)) {
            $club = Club::where('custom_domain', $host)
                ->where('domain_status', 'active')
                ->first();

            if ($club) {
                // Attach resolved tenant to request attributes
                $request->attributes->set('tenant_club', $club);
            }
        }

        return $next($request);
    }
}
