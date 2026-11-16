<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Launchpad;

/** The three pages of email verification: the notice ("check your inbox"), the resend, and the link in the mail */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|Responsable|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended(config('launchpad.routes.after_login'))
            : Ui::render('auth.verify-email', ['email' => $request->user()->email]);
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(config('launchpad.routes.after_login'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(['verified' => $request->user()->hasVerifiedEmail()]);
    }

    /**
     * The link works from any browser, signed in or not (the phone's mail app is not the browser they signed up in). What it checks is
     * the signature (the route's middleware) and that it was made for the address this account has now: a link for an address that has
     * since been changed verifies nothing.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = Launchpad::userModel()::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            // what hangs on a proved address (an invitation waiting for it) listens for this
            event(new Verified($user));
            // a deadline is cleared when the address is verified, so a later change of email never opens a new grace period
            $user->forceFill(['verify_by' => null])->save();
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(config('launchpad.routes.after_login').'?verified=1');
        }

        return redirect()->route('login')->with('status', __('Your email address is verified. Please sign in.'));
    }
}
