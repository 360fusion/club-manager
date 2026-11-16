<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Site\Consent;

class ConsentController extends Controller
{
    public function show(): View|Responsable
    {
        return Ui::render('site.cookie-preferences', [], fn () => [
            'categories' => collect(Consent::CATEGORIES)->map(fn ($key) => ['key' => $key, 'label' => Consent::label($key), 'granted' => Consent::granted($key)])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['choice' => ['required', 'in:all,necessary,custom']]);

        $categories = match ($request->input('choice')) {
            'all' => Consent::CATEGORIES,
            'custom' => (array) $request->input('categories', []),
            default => [],
        };

        // six months; readable by the page's own scripts is not needed, so not for JavaScript
        return redirect()->back(fallback: '/')->withCookie(cookie(Consent::COOKIE, Consent::value($categories), 60 * 24 * 182, '/', null, $request->isSecure(), true, false, 'lax'));
    }
}
