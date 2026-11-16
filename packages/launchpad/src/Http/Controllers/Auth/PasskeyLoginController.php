<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Launchpad\Accounts\Passkeys\Passkeys;

/** Sign-in with a passkey: the page asks for options, the browser signs them, and the answer is posted back (both as JSON) */
class PasskeyLoginController
{
    public function options(Passkeys $passkeys): JsonResponse
    {
        return response()->json(json_decode($passkeys->authenticationOptions()));
    }

    public function store(Request $request, Passkeys $passkeys): JsonResponse
    {
        $request->validate(['credential' => ['required', 'string', 'json', 'max:20000']]);

        $user = $passkeys->find($request->string('credential')->toString())?->authenticatable;

        if ($user === null) {
            return response()->json(['message' => __('That passkey could not be used. Try again, or sign in another way.')], 422);
        }

        if ($user->isClosed()) {
            return response()->json(['message' => __('This account has been closed.')], 422);
        }

        if ($user->isSuspended()) {
            return response()->json(['message' => __('This account has been suspended. Contact support if you think this is a mistake.')], 422);
        }

        // the device's lock was required, so this is two factors already: no two-factor step
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['redirect' => redirect()->intended(config('launchpad.routes.after_login'))->getTargetUrl()]);
    }
}
