<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Launchpad\Accounts\Turnstile;
use Launchpad\Accounts\Waitlist;
use Launchpad\Http\Ui;

class WaitlistController
{
    public function create(): View|Responsable
    {
        return Ui::render('auth.waitlist', ['turnstileKey' => Turnstile::siteKey()]);
    }

    public function store(Request $request, Turnstile $turnstile, Waitlist $waitlist): RedirectResponse
    {
        $turnstile->verify($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'name' => ['nullable', 'string', 'max:255']]);

        $waitlist->join($data['email'], $data['name'] ?? null);

        // the same answer whether or not the address was on it already
        return back()->with('status', __('You are on the list. We will email you when there is room.'));
    }
}
