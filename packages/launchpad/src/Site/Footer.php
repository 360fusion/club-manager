<?php

namespace Launchpad\Site;

use Illuminate\Support\Facades\Route;

/** The footer's link columns: config launchpad.site.footer, plus a Legal column, without the links to anything that is switched off */
final class Footer
{
    private const ROUTES = ['/pricing' => 'pricing', '/help' => 'help', '/faq' => 'faq', '/contact' => 'contact'];

    /** @return array<string, list<array{0: string, 1: string}>> */
    public static function columns(): array
    {
        $columns = (array) config('launchpad.site.footer');
        $listed = collect($columns)->flatten(1)->map(fn ($link) => $link[1]);
        $columns['Legal'] = collect((array) config('launchpad.site.legal'))->reject(fn ($slug) => $listed->contains('/'.$slug))
            ->map(fn ($slug) => [$slug === 'dpa' ? 'DPA' : ucfirst(str_replace('-', ' ', $slug)), '/'.$slug])->all();

        return array_filter(array_map(fn ($links) => array_values(array_filter($links, self::on(...))), $columns));
    }

    private static function on(array $link): bool
    {
        $slug = ltrim($link[1], '/');
        $pages = (array) config('launchpad.site.pages');

        if (array_key_exists($slug, $pages)) {
            return (bool) $pages[$slug];
        }

        return ! isset(self::ROUTES[$link[1]]) || Route::has(self::ROUTES[$link[1]]);
    }
}
