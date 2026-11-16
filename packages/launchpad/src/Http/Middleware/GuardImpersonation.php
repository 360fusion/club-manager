<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Launchpad\Admin\Impersonation;
use Launchpad\Admin\StaffAccess;
use Symfony\Component\HttpFoundation\Response;

/**
 * While staff are signed in as a customer: it ends when the staff member is no longer signed in to the admin with the permission, or after
 * the time in config launchpad.impersonation.minutes, and it refuses the actions listed in launchpad.impersonation.blocked.
 */
class GuardImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active()) {
            return $next($request);
        }

        $staff = Impersonation::staff();
        $expired = now()->timestamp - Impersonation::current()['started_at'] > 60 * (int) config('launchpad.impersonation.minutes', 60);

        if (! $staff || ! StaffAccess::can($staff, 'impersonate') || $expired) {
            Impersonation::stop();

            return redirect('/')->with('status', 'Signing in as that person has ended.');
        }

        if (! $request->isMethodSafe() && $request->route()?->getName() !== 'impersonate.stop') {
            foreach ((array) config('launchpad.impersonation.blocked') as $pattern) {
                if ($request->routeIs($pattern)) {
                    abort(403, 'This cannot be done while you are signed in as someone else.');
                }
            }
        }

        return $next($request);
    }
}
