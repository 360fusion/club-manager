<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Turnstile;
use Launchpad\Http\Ui;

class PasswordResetLinkController extends Controller
{
    public function create(): View|Responsable
    {
        return Ui::render('auth.forgot-password', ['turnstileKey' => Turnstile::siteKey()]);
    }

    /**
     * It answers the same whether or not the address has an account (saying "we can't find that address" is a way to find out who is
     * here), and a closed account is not sent a link.
     */
    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        $turnstile->verify($request);

        $request->validate(['email' => 'required|email']);

        $account = AccountRegistration::findByEmail($request->input('email'));

        if (! ($account && method_exists($account, 'isClosed') && $account->isClosed())) {
            Password::sendResetLink(['email' => $account?->email ?? AccountRegistration::normalizeEmail($request->input('email'))]);
        }

        return back()->with('status', __('If that address has an account, we have emailed it a link to choose a new password.'));
    }
}
