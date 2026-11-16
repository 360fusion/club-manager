<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Http\Ui;
use Launchpad\Launchpad;

/**
 * The second step of signing in, for an account that has two-factor on: the code from the app, or a recovery code. The password was
 * already checked (LoginRequest put the account's id in the session as `login.id`).
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|Responsable|RedirectResponse
    {
        return $request->session()->has('login.id') ? Ui::render('auth.two-factor-challenge') : redirect()->route('login');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $request->validate(['code' => 'nullable|string|max:20', 'recovery_code' => 'nullable|string|max:64']);

        $user = Launchpad::userModel()::find($request->session()->get('login.id'));

        if (! $user || ! $twoFactor->isOn($user) || (method_exists($user, 'isClosed') && $user->isClosed())) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        if ($twoFactor->lockedOut($user)) {
            $request->session()->forget(['login.id', 'login.remember']);

            throw ValidationException::withMessages([
                'code' => trans_choice('Too many wrong codes. Try again in :count minute.|Too many wrong codes. Try again in :count minutes.', (int) ceil($twoFactor->lockedOutForSeconds($user) / 60)),
            ]);
        }

        $field = $request->filled('recovery_code') ? 'recovery_code' : 'code';

        $valid = $field === 'recovery_code'
            ? $twoFactor->useRecoveryCode($user, (string) $request->recovery_code)
            : $twoFactor->verifyCode($user, (string) $request->code);

        if (! $valid) {
            $twoFactor->wrongAnswer($user);

            throw ValidationException::withMessages([$field => __('The two-factor code was not right.')]);
        }

        $twoFactor->rightAnswer($user);

        Auth::login($user, (bool) $request->session()->get('login.remember'));
        $request->session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();

        return redirect()->intended(config('launchpad.routes.after_login'));
    }
}
