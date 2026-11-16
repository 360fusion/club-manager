<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Launchpad\Http\Ui;

class NewPasswordController extends Controller
{
    public function create(Request $request): View|Responsable
    {
        return Ui::render('auth.reset-password', ['email' => $request->query('email'), 'token' => $request->route('token')]);
    }

    /** @throws ValidationException */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    // the remember cookie of every other browser stops working
                    'remember_token' => Str::random(60),
                    'password_set_at' => $user->freshTimestamp(),
                ])->save();

                // the link came to the inbox, so it proves the address
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                    event(new Verified($user));
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages(['email' => [trans($status)]]);
    }
}
