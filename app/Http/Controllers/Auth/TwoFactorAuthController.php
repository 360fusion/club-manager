<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorAuth\ConfirmRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorAuthController extends Controller
{
    /**
     * Show 2FA settings management page for current admin/user.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $enabled = ! empty($user->two_factor_secret);
        $confirmed = ! empty($user->two_factor_confirmed_at);

        // The secret is only needed while setting up, and recovery codes are shown once, right after they are made:
        // anyone who later reaches an open session should not be able to read them off this page.
        $pending = $enabled && ! $confirmed;
        $qrCodeSvg = $pending ? $user->twoFactorQrCodeSvg() : null;
        $secretKey = $pending ? decrypt($user->two_factor_secret) : null;
        $recoveryCodes = $request->session()->get('recoveryCodes', []);

        return Inertia::render('Admin/Profile/TwoFactorSetting', [
            'user' => $user,
            'twoFactorEnabled' => $enabled && $confirmed,
            'twoFactorPending' => $enabled && ! $confirmed,
            'qrCodeSvg' => $qrCodeSvg,
            'secretKey' => $secretKey,
            'recoveryCodes' => $recoveryCodes,
            'recoveryCodesLeft' => $enabled && $confirmed ? count($user->recoveryCodes()) : 0,
        ]);
    }

    /**
     * Enable 2FA for current user.
     */
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user());

        return redirect()->back()->with('success', 'Two-Factor Authentication initiated. Scan QR code to confirm.');
    }

    /**
     * Confirm 2FA setup with a 6-digit TOTP code.
     */
    public function confirm(ConfirmRequest $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {

        $confirm($request->user(), $request->code);

        return redirect()->back()
            ->with('success', 'Two-Factor Authentication confirmed and activated!')
            ->with('recoveryCodes', $request->user()->fresh()->recoveryCodes());
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $disable($request->user());

        return redirect()->back()->with('success', 'Two-Factor Authentication disabled.');
    }

    /**
     * Generate new recovery codes.
     */
    public function generateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $generate($request->user());

        return redirect()->back()
            ->with('success', 'New recovery codes generated. Store them now: they are not shown again.')
            ->with('recoveryCodes', $request->user()->fresh()->recoveryCodes());
    }

    /**
     * Sign out every other browser or phone signed in to this account (AuthenticateSession ends them on their next request).
     */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->password);

        return redirect()->back()->with('success', 'You have been signed out everywhere else.');
    }

    /**
     * Show 2FA challenge login page.
     */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }
}
