<?php

namespace App\Http\Controllers;

use App\Models\DeviceSignInKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Face ID and fingerprint sign-in in the iOS and Android app. Turning it on gives this phone a long random key; the app
 * keeps it in the Keychain or Keystore, readable only after Face ID or a fingerprint (the AppLock plugin in mobile/).
 * Signing in sends the key back. A new password, turning it off, or six months unused removes it.
 */
class DeviceSignInController extends Controller
{
    /** How long after signing in the app may turn this on without asking for the password again. */
    private const RECENT_SIGN_IN_SECONDS = 900;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in(DeviceSignInKey::PLATFORMS)],
            'name' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();

        // A session alone is not enough to mint a key that outlives it: the person must have just signed in, or give the password.
        $signedInAt = (int) $request->session()->get('auth.signed_in_at', 0);
        if (time() - $signedInAt > self::RECENT_SIGN_IN_SECONDS && ! Hash::check((string) ($validated['password'] ?? ''), (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'Enter your password to turn this on.']);
        }

        $key = Str::random(64);
        $record = $user->deviceSignInKeys()->create([
            'key_hash' => DeviceSignInKey::hashOf($key),
            'platform' => $validated['platform'],
            'name' => $validated['name'] ?? null,
        ]);

        $keep = $user->deviceSignInKeys()->latest('id')->limit(DeviceSignInKey::MAX_PER_USER)->pluck('id');
        $user->deviceSignInKeys()->whereNotIn('id', $keep)->delete();

        return response()->json(['key' => $key, 'id' => $record->id]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->deviceSignInKeys()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Sign in with the key this phone unlocked with Face ID or a fingerprint. A key that no longer works is reported as
     * `device_key`, so the app forgets it and offers the password.
     */
    public function signIn(Request $request): RedirectResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'size:64']]);
        $record = DeviceSignInKey::with('user')->where('key_hash', DeviceSignInKey::hashOf($validated['key']))->first();

        if (! $record?->user || $record->isStale()) {
            $record?->delete();

            throw ValidationException::withMessages(['device_key' => 'Face ID and fingerprint sign-in has been turned off for this phone. Sign in with your password.']);
        }

        Auth::login($record->user, remember: true);
        $request->session()->regenerate();
        $record->forceFill(['last_used_at' => now()])->save();

        return redirect()->intended(route('members.dashboard'));
    }
}
