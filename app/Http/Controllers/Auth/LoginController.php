<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'demoCredentials' => app()->isLocal()
                ? ['email' => 'admin@example.com', 'password' => 'password']
                : null,
        ]);
    }

    /**
     * Handle an authentication request.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::validate($credentials)) {
            $user = User::where('email', $request->email)->first();

            if (EmailVerification::required() && ! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                return back()->withErrors([
                    'email' => 'Please confirm your email address first. We have sent you a new confirmation link.',
                ]);
            }

            if (! empty($user->two_factor_secret) && ! empty($user->two_factor_confirmed_at)) {
                $request->session()->put('login.id', $user->id);
                $request->session()->put('login.remember', $request->boolean('remember'));

                return redirect()->route('two-factor.challenge');
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('members.dashboard'))->with('success', 'Logged in successfully!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }
}
