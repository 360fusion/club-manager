<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->is_super_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Superadmin privileges required.'], 403);
            }

            return redirect()->route('home')->with('error', 'Unauthorized. Superadmin access required.');
        }

        // Platform-wide access needs a second factor on every real server; local, test and e2e databases are exempt.
        if (! app()->environment(['local', 'testing', 'e2e']) && ! $request->user()->two_factor_confirmed_at) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Turn on two-factor authentication to use the superadmin area.'], 403);
            }

            return redirect()->route('admin.profile.two-factor')->with('error', 'Turn on two-factor authentication before using the superadmin area.');
        }

        return $next($request);
    }
}
