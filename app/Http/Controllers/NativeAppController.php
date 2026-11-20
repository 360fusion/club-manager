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
use Inertia\Inertia;
use Inertia\Response;

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
            'platform' => ['required', Rule::in(PushDevice::PLATFORMS)],
            'token' => ['exclude_if:platform,web', 'required', 'string', 'max:255'],
            // A browser's push subscription: only the browsers' own push services are accepted (PushDevice::WEB_PUSH_HOSTS).
            'subscription' => ['exclude_unless:platform,web', 'required', 'array:endpoint,expirationTime,keys'],
            'subscription.endpoint' => ['exclude_unless:platform,web', 'required', 'string', 'max:2000', 'url:https', fn (string $attribute, mixed $value, \Closure $fail) => preg_match(PushDevice::WEB_PUSH_HOSTS, (string) parse_url((string) $value, PHP_URL_HOST)) ? null : $fail('That is not a push service this site sends to.')],
            'subscription.keys.p256dh' => ['exclude_unless:platform,web', 'required', 'string', 'max:200'],
            'subscription.keys.auth' => ['exclude_unless:platform,web', 'required', 'string', 'max:100'],
        ]);

        $web = $validated['platform'] === 'web';
        $token = $web ? PushDevice::webToken($validated['subscription']['endpoint']) : $validated['token'];

        // A token belongs to one phone; if someone else signs in on it, it moves to them.
        PushDevice::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'subscription' => $web ? ['endpoint' => $validated['subscription']['endpoint'], 'keys' => $validated['subscription']['keys']] : null,
                'last_seen_at' => now(),
            ],
        );
        // A person's browsers are kept to the ten used most lately, so nobody can pile up subscriptions to send to.
        if ($web) {
            $keep = $request->user()->pushDevices()->where('platform', 'web')->latest('last_seen_at')->latest('id')->limit(PushDevice::MAX_WEB_PER_USER)->pluck('id');
            $request->user()->pushDevices()->where('platform', 'web')->whereNotIn('id', $keep)->delete();
        }

        // Signing out on this phone forgets the token (ForgetPushDeviceOnLogout), so the next person to sign in doesn't get these pushes.
        $request->session()->put('push_token', $token);

        return response()->json(['ok' => true]);
    }

    public function destroyDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required_without:endpoint', 'string', 'max:255'],
            'endpoint' => ['required_without:token', 'string', 'max:2000'],
        ]);

        $token = isset($validated['endpoint']) ? PushDevice::webToken($validated['endpoint']) : $validated['token'];
        $request->user()->pushDevices()->where('token', $token)->delete();

        return response()->json(['ok' => true]);
    }

    /** The app's own settings: which notifications reach the phone, and the Face ID or fingerprint lock (kept on the phone). */
    public function settings(Request $request): Response
    {
        return Inertia::render('Members/AppSettings', ['pushMuted' => $request->user()->push_muted ?? []]);
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
