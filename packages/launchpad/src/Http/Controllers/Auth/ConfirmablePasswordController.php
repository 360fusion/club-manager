<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Launchpad\Accounts\PasswordCheck;
use Launchpad\Http\Ui;

class ConfirmablePasswordController extends Controller
{
    public function show(): View|Responsable
    {
        return Ui::render('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! PasswordCheck::verify($request->user(), (string) $request->password)) {
            throw ValidationException::withMessages(['password' => trans('auth.password')]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(config('launchpad.routes.after_login'));
    }
}
