<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorAuth\ConfirmRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'twoFactorPending' => $enabled && ! $confirmed,
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
    public function confirm(ConfirmRequest $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {

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
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }
}
