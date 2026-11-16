<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Launchpad\Accounts\DeviceSessions;
use Launchpad\Accounts\Passkeys\Passkeys;
use Launchpad\Accounts\PasswordCheck;
use Launchpad\Accounts\SocialProviders;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Http\Ui;
use Launchpad\Models\AccountEvent;

/** The Security page: the password, and two-factor sign-in */
class SecurityController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor): View|Responsable
    {
        $user = $request->user();
        $pending = filled($user->two_factor_secret) && ! $twoFactor->isOn($user);

        $data = [
            'user' => $user,
            'twoFactorOn' => $twoFactor->isOn($user),
            // the QR code of a secret that no code has confirmed yet
            'qrCode' => $pending ? $twoFactor->qrCodeSvg($user) : null,
            'secret' => $pending ? $user->two_factor_secret : null,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
            'needsCurrentPassword' => $user->password_set_at !== null,
            'connections' => $this->connections($user),
            'devices' => DeviceSessions::available() ? DeviceSessions::for($user, $request->session()->getId()) : null,
            'passkeys' => Passkeys::enabled() ? $user->passkeys()->latest()->get(['id', 'name', 'created_at', 'last_used_at']) : null,
        ];

        return Ui::render('account.security', $data, fn () => [
            'twoFactorOn' => $data['twoFactorOn'],
            'qrCode' => $data['qrCode'],
            'secret' => $data['secret'],
            'recoveryCodes' => $data['recoveryCodes'],
            'recoveryLeft' => count($user->two_factor_recovery_codes ?? []),
            'needsCurrentPassword' => $data['needsCurrentPassword'],
            'connections' => $data['connections'],
            'passkeys' => $data['passkeys']?->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'added' => $p->created_at->toIso8601String(), 'lastUsed' => $p->last_used_at?->toIso8601String()])->all(),
            'devices' => $data['devices'] === null ? null : array_map(fn ($d) => ['device' => $d['device'], 'ip' => $d['ip'], 'lastActive' => $d['last_active']->toIso8601String(), 'current' => $d['current']], $data['devices']),
        ]);
    }

    /** @return list<array{provider: string, name: string, linked: bool}> */
    private function connections($user): array
    {
        $linked = Schema::hasTable('social_accounts') ? $user->socialAccounts()->pluck('provider')->all() : [];

        return collect(SocialProviders::NAMES)
            ->filter(fn ($name, $provider) => in_array($provider, $linked, true) || SocialProviders::configured($provider))
            ->map(fn ($name, $provider) => ['provider' => $provider, 'name' => $name, 'linked' => in_array($provider, $linked, true)])->values()->all();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            // a sign-in with Google gave the account a random password nobody knows: a first one needs no current one
            'current_password' => $user->password_set_at !== null ? ['required', fn ($attribute, $value, $fail) => PasswordCheck::verify($user, (string) $value) ?: $fail(__('The password is not right.'))] : ['nullable'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($request->password), 'password_set_at' => $user->freshTimestamp()])->save();
        AccountEvent::record($user, 'password_changed');

        return back()->with('status', 'password-updated');
    }

    public function enableTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if (! $twoFactor->isOn($user)) {
            $user->forceFill([
                'two_factor_secret' => $twoFactor->newSecret(),
                'two_factor_recovery_codes' => $twoFactor->newRecoveryCodes(),
                'two_factor_confirmed_at' => null,
            ])->save();
            $twoFactor->forgetAcceptedSteps($user);
        }

        return redirect()->route('account.security');
    }

    public function confirmTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['code' => 'required|string|max:20']);

        if (! filled($user->two_factor_secret) || ! $twoFactor->verifyCode($user, (string) $request->code)) {
            throw ValidationException::withMessages(['code' => __('The two-factor code was not right.')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => $user->freshTimestamp()])->save();
        AccountEvent::record($user, 'two_factor_on');

        // shown once, here, then only the count is
        return redirect()->route('account.security')->with('recovery_codes', $user->two_factor_recovery_codes);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();

        abort_unless($twoFactor->isOn($user), 404);

        $user->forceFill(['two_factor_recovery_codes' => $twoFactor->newRecoveryCodes()])->save();

        return redirect()->route('account.security')->with('recovery_codes', $user->two_factor_recovery_codes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        AccountEvent::record($request->user(), 'two_factor_off');

        return redirect()->route('account.security')->with('status', 'two-factor-disabled');
    }
}
