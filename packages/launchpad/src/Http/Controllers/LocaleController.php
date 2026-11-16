<?php

namespace Launchpad\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Launchpad\Support\Locales;

/** Choosing the language of the site: kept in the session, and on the person's account when signed in */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(array_keys(Locales::supported()))]]);

        $request->session()->put('launchpad.locale', $data['locale']);
        $request->user()?->forceFill(['locale' => $data['locale']])->save();

        // back to the page it was chosen on, but only when that page is ours
        $referer = (string) $request->headers->get('referer');

        $base = $request->getSchemeAndHttpHost();

        return redirect()->to($referer === $base || str_starts_with($referer, $base.'/') ? $referer : '/');
    }
}
