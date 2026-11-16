<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Launchpad\Accounts\Turnstile;
use Launchpad\Http\Requests\LoginRequest;
use Launchpad\Http\Ui;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View|Responsable
    {
        return Ui::render('auth.login', [
            'canResetPassword' => Route::has('password.request'),
            'turnstileKey' => LoginRequest::challengeRequired($request) ? Turnstile::siteKey() : null,
            'canUseSso' => Route::has('sso.create'),
            'canUsePasskeys' => Route::has('passkey.login'),
            'canUseLoginLinks' => Route::has('login.link'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if ($request->authenticate() !== null) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(config('launchpad.routes.after_login'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(config('launchpad.routes.home'));
    }
}
