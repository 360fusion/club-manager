<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Launchpad\Accounts\LoginLinks;
use Launchpad\Accounts\Turnstile;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Http\Ui;

/** "Email me a sign-in link": ask for one, then open it and press Sign in */
class LoginLinkController
{
    public function create(): View|Responsable
    {
        return Ui::render('auth.login-link', ['turnstileKey' => Turnstile::siteKey()]);
    }

    public function store(Request $request, Turnstile $turnstile, LoginLinks $links): RedirectResponse
    {
        $turnstile->verify($request);
        $request->validate(['email' => 'required|email']);

        $links->send($request->string('email')->toString());

        return back()->with('status', __('If that address has an account, we have emailed it a sign-in link. It lasts :minutes minutes.', ['minutes' => LoginLinks::MINUTES]));
    }

    public function show(Request $request): View|Responsable
    {
        return Ui::render('auth.login-link-confirm', ['action' => $request->fullUrl()]);
    }

    public function consume(Request $request, string $token, LoginLinks $links, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $links->consume($token);

        if ($user === null) {
            return redirect()->route('login.link')->withErrors(['email' => __('That sign-in link has been used or has expired. Ask for a new one.')]);
        }

        if ($user->isClosed()) {
            return redirect()->route('login')->withErrors(['email' => __('This account has been closed.')]);
        }

        if ($user->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => __('This account has been suspended. Contact support if you think this is a mistake.')]);
        }

        // the link reached the inbox, so the address is proved
        $user->hasVerifiedEmail() || $user->markEmailAsVerified();

        if ($twoFactor->isOn($user)) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('launchpad.routes.after_login'));
    }
}
