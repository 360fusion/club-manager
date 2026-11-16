<?php

namespace Launchpad\Testing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Launchpad\Http\Middleware\AuthenticateScim;

/**
 * Use in a feature test of the app: `use AssertsRoutesAreClassified;` then call `$this->assertEveryRouteIsClassified()`.
 *
 * Fails for any route that is neither behind a sign-in (`auth`), a guest-only page (`guest`), signed, a workspace route, nor marked public on
 * purpose (`launchpad.public`), or a token-guarded API route (`launchpad.api`). A new route that nobody classified is open to the world by accident, and this is how that gets noticed.
 */
trait AssertsRoutesAreClassified
{
    /** @param  list<string>  $ignoredPrefixes  route names or paths (with a trailing *) that are not the app's own */
    protected function assertEveryRouteIsClassified(array $ignoredPrefixes = ['storage.*', 'sanctum.*', 'ignition.*', 'livewire.*', 'livewire-*', 'livewire-*/*', 'filament.*', 'horizon.*', 'pulse', 'telescope*', 'up']): void
    {
        $unclassified = collect(Routes::getRoutes()->getRoutes())
            ->reject(fn (Route $route) => $this->isIgnored($route, $ignoredPrefixes) || $this->isClassified($route))
            ->map(fn (Route $route) => implode('|', $route->methods()).' /'.ltrim($route->uri(), '/'))
            ->values();

        $this->assertSame([], $unclassified->all(), "These routes are not classified (add auth, guest, signed, workspace, launchpad.api or launchpad.public):\n".$unclassified->implode("\n"));
    }

    private function isClassified(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            // a team directory's SCIM token is a sign-in of its own
            if (preg_match('/^(auth|guest|signed|workspace|launchpad\.public|launchpad\.api)(:|$)/', $middleware) || str_starts_with($middleware, 'auth.') || $middleware === AuthenticateScim::class) {
                return true;
            }
        }

        return false;
    }

    private function isIgnored(Route $route, array $ignored): bool
    {
        foreach ($ignored as $pattern) {
            if (fnmatch($pattern, (string) $route->getName()) || fnmatch($pattern, ltrim($route->uri(), '/'))) {
                return true;
            }
        }

        return false;
    }
}
