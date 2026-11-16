<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Launchpad\Accounts\SignupSettings;
use Launchpad\Accounts\VerificationGrace;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an account that has not proved its email address from using the app, as far as the admin's setting says (SignupSettings:
 * required or soft). One class, two ways of being used:
 *
 *  - The gate, with no parameter, goes in the `web` group. It acts on a route only when that route sits behind `auth` (so the public
 *    site, webhooks and the API are untouched), and only in "required" mode. It is deny-by-default: a new authenticated route is
 *    covered without anyone having to remember a middleware.
 *  - The alias `verified.policy:action` and `verified.policy:strict` go on the routes that send mail, post in public or take money. They
 *    hold in "soft" mode too. `action` lets an account inside its grace period through, `strict` does not.
 *
 * A team member is never held, and neither is an account that is verified.
 */
class EnsureEmailIsVerifiedByPolicy
{
    public function handle(Request $request, Closure $next, ?string $flavour = null): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail() || (method_exists($user, 'isStaff') && $user->isStaff())) {
            return $next($request);
        }

        $mode = SignupSettings::current()->effective();

        if ($mode === 'off') {
            return $next($request);
        }

        $held = match ($flavour) {
            null => $mode === 'required' && $this->behindAuth($request) && ! $this->isAllowed($request),
            default => true,
        };

        if (! $held || ($flavour !== 'strict' && VerificationGrace::covers($user))) {
            return $next($request);
        }

        return $this->refuse($request);
    }

    private function behindAuth(Request $request): bool
    {
        $route = $request->route();

        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || str_starts_with($middleware, Authenticate::class))) {
                return true;
            }
        }

        return false;
    }

    private function isAllowed(Request $request): bool
    {
        return $request->routeIs(...(array) config('launchpad.verification.allowed_routes', []))
            || $request->is(...(array) config('launchpad.verification.allowed_paths', []));
    }

    private function refuse(Request $request): Response
    {
        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            abort(403, 'Your email address is not verified.');
        }

        // Like Laravel's own `verified`: remember where they were going, and send them to say who they are
        return redirect()->guest(route('verification.notice'));
    }
}
