<?php

namespace Launchpad\Testing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Launchpad\Api\OpenApi;

/**
 * Use in a feature test of the app: `use AssertsApiIsDescribed;` then call `$this->assertEveryApiRouteIsDescribed()`. Fails for any /api/v1 route with
 * no entry in the API description (resources/openapi/*.yaml), so the published docs never miss an endpoint.
 */
trait AssertsApiIsDescribed
{
    /** @param  list<string>  $ignore  routes to leave out, as "GET /path" */
    protected function assertEveryApiRouteIsDescribed(array $ignore = []): void
    {
        $paths = (array) (OpenApi::build()['paths'] ?? []);
        $missing = [];

        foreach (Routes::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/v1/') || $route->getName() === 'launchpad.api.openapi') {
                continue;
            }

            $path = substr($route->uri(), strlen('api/v1'));
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $entry = "{$method} {$path}";
                if (! isset($paths[$path][strtolower($method)]) && ! in_array($entry, $ignore, true)) {
                    $missing[] = $entry;
                }
            }
        }

        $this->assertSame([], $missing, "These API routes are not described in resources/openapi/*.yaml:\n".implode("\n", $missing));
    }
}
