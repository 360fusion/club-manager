<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorAuthController extends Controller
{
    /**
     * Show 2FA settings management page for current admin/user.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $enabled = !empty($user->two_factor_secret);
        $confirmed = !empty($user->two_factor_confirmed_at);

        $qrCodeSvg = null;
        $secretKey = null;
        $recoveryCodes = [];

        if ($enabled) {
            $qrCodeSvg = $user->twoFactorQrCodeSvg();
            $secretKey = decrypt($user->two_factor_secret);
            $recoveryCodes = $user->recoveryCodes();
        }

        return Inertia::render('Admin/Profile/TwoFactorSetting', [
            'user' => $user,
            'twoFactorEnabled' => $enabled && $confirmed,
            'twoFactorPending' => $enabled && !$confirmed,
            'qrCodeSvg' => $qrCodeSvg,
            'secretKey' => $secretKey,
            'recoveryCodes' => $recoveryCodes,
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
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $confirm($request->user(), $request->code);
        return redirect()->back()->with('success', 'Two-Factor Authentication confirmed and activated!');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $disable($request->user());
        return redirect()->back()->with('success', 'Two-Factor Authentication disabled.');
    }

    /**
     * Generate new recovery codes.
     */
    public function generateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $generate($request->user());
        return redirect()->back()->with('success', 'New recovery codes generated.');
    }

    /**
     * Show 2FA challenge login page.
     */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify 2FA challenge login.
     */
    public function verifyChallenge(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $userId = $request->session()->get('login.id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $code = $request->input('code');
        $recoveryCode = $request->input('recovery_code');

        $valid = false;

        if ($code) {
            $valid = $provider->verify(decrypt($user->two_factor_secret), $code);
        } elseif ($recoveryCode && $user->recoveryCodes()) {
            $valid = in_array($recoveryCode, $user->recoveryCodes(), true);
        }

        if (!$valid) {
            return redirect()->back()->withErrors(['code' => 'The provided two-factor authentication code was invalid.']);
        }

        $request->session()->forget('login.id');
        Auth::login($user, $request->session()->get('login.remember', false));

        return redirect()->intended(route('home'));
    }
}
