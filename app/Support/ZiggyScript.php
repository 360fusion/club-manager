<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tighten\Ziggy\BladeRouteGenerator;

/**
 * Ziggy's route table for the page's <script>, built once and reused. Building it walks every route (about 600) on
 * each full page load, which costs more than the rest of a simple page; the table only changes when the routes do,
 * so it is kept per route group and site address and rebuilt when any route's name or address changes.
 */
final class ZiggyScript
{
    public static function render(?string $group): string
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->getName().' '.$route->uri())->implode("\n");
        $key = 'ziggy:'.($group ?? 'all').':'.md5(url('/').'|'.$routes);

        return Cache::rememberForever($key, function () use ($group) {
            // Ziggy leaves out its route function once a process has printed one (a queue worker builds many pages),
            // and the copy kept here must always be the whole script.
            BladeRouteGenerator::$generated = false;

            return app(BladeRouteGenerator::class)->generate($group);
        });
    }
}
