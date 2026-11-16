<?php

namespace Launchpad\Http;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Draws a page of the package in whichever way the app asked for (config launchpad.ui): `blade`, the package's own views, or `inertia`, a Vue page
 * called Launchpad/<Area>/<Page> given plain props. The page's data is the same either way; the Inertia props are only what the page shows, as plain
 * values (never a model or a service), so a Vue page cannot be handed something it should not see.
 */
final class Ui
{
    public static function inertia(): bool
    {
        return config('launchpad.ui') === 'inertia' && class_exists(Inertia::class);
    }

    /** The Vue component of a view: 'auth.login' is 'Launchpad/Auth/Login', 'help.faq-entry' is 'Launchpad/Help/FaqEntry' */
    public static function component(string $view): string
    {
        return 'Launchpad/'.Str::of($view)->explode('.')->map(fn ($part) => Str::studly($part))->implode('/');
    }

    /**
     * @param  array<string, mixed>  $data  what the Blade view is given
     * @param  Closure(): array<string, mixed>|null  $props  what the Vue page is given, when that is not simply $data
     */
    public static function render(string $view, array $data = [], ?Closure $props = null): View|Responsable
    {
        if (! self::inertia()) {
            return view("launchpad::{$view}", $data);
        }

        return Inertia::render(self::component($view), Props::from($props ? $props() : $data));
    }

    /** A redirect to another site (Stripe): an Inertia visit cannot follow one, so it is told to leave the page instead */
    public static function away(string $url): RedirectResponse|Response
    {
        if (self::inertia() && request()->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->away($url);
    }
}
