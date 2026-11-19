<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use App\Notifications\ClubNotification;
use App\Support\NativeApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * What only the iOS and Android app needs: the way back into it after paying in the phone's
 * browser, and the push token of each phone it is signed in on.
 */
class NativeAppController extends Controller
{
    /**
     * Stripe and PayPal send an app user here; the page opens the app at `to`, keeping
     * whatever the payment page added to the address (PayPal's `token`).
     */
    public function return(Request $request): View
    {
        $path = NativeApp::safePath($request->query('to')) ?? route('members.dashboard', [], false);
        $extra = http_build_query($request->except('to'));

        if ($extra !== '') {
            $path .= (str_contains($path, '?') ? '&' : '?').$extra;
        }

        return view('app-return', ['deepLink' => NativeApp::deepLink($path)]);
    }

    public function storeDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(PushDevice::PLATFORMS)],
        ]);

        // A token belongs to one phone; if someone else signs in on it, it moves to them.
        PushDevice::updateOrCreate(
            ['token' => $validated['token']],
            ['user_id' => $request->user()->id, 'platform' => $validated['platform'], 'last_seen_at' => now()],
        );
        // Signing out on this phone forgets the token (ForgetPushDeviceOnLogout), so the next person to sign in doesn't get these pushes.
        $request->session()->put('push_token', $validated['token']);

        return response()->json(['ok' => true]);
    }

    public function destroyDevice(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->pushDevices()->where('token', $validated['token'])->delete();

        return response()->json(['ok' => true]);
    }

    /** Which kinds of inbox notification stay off the person's phones. They still reach the inbox. */
    public function updatePushSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'muted' => ['nullable', 'array'],
            'muted.*' => [Rule::in(ClubNotification::PUSH_OPTIONAL)],
        ]);

        $request->user()->forceFill(['push_muted' => array_values(array_unique($validated['muted'] ?? []))])->save();

        return back()->with('success', 'Phone notification settings saved.');
    }
}
