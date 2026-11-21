<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

/**
 * Ziggy's route table for the page. It is large (every route a visitor's group may use, about 600KB for staff), so on
 * the app's own address it is a separate file whose address carries a hash of the routes: a phone downloads it once
 * and keeps it until a deploy changes the routes, instead of reading it inside every page it opens. Like Ziggy's own
 * inline script, the file sets the table and the global route() function that page scripts call.
 *
 * Where a separate file cannot be fetched it stays inside the page: a static website export, and a lodge's own domain
 * (which sends app addresses on to the main site).
 */
final class ZiggyScript
{
    public static function render(?string $group): string
    {
        $request = request();
        $ownAddress = $request->getHost() === parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($request->attributes->get('static_site') || ! $ownAddress) {
            return self::inline($group);
        }

        return '<script src="'.e(route('ziggy.routes', ['group' => $group ?? 'all', 'hash' => self::hash()], false)).'"></script>';
    }

    /**
     * The file's contents: the table and the global route() function, as Ziggy's inline script has them.
     */
    public static function body(?string $group): string
    {
        return Cache::rememberForever('ziggy-js:'.($group ?? 'all').':'.self::hash(), fn () => 'const Ziggy = '.(new Ziggy($group))->toJson().";\n"
            .file_get_contents(base_path('vendor/tightenco/ziggy/dist/route.umd.js')));
    }

    /**
     * Changes whenever a route's name or address, the site's address, or Ziggy's route() function changes, so a kept
     * copy is never out of date.
     */
    public static function hash(): string
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->getName().' '.$route->uri())->implode("\n");

        return substr(md5(url('/').'|'.md5_file(base_path('vendor/tightenco/ziggy/dist/route.umd.js')).'|'.$routes), 0, 16);
    }

    private static function inline(?string $group): string
    {
        return Cache::rememberForever('ziggy:'.($group ?? 'all').':'.self::hash(), function () use ($group) {
            // Ziggy leaves out its route function once a process has printed one (a queue worker builds many pages),
            // and the copy kept here must always be the whole script.
            BladeRouteGenerator::$generated = false;

            return app(BladeRouteGenerator::class)->generate($group);
        });
    }
}
