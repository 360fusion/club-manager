<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Launchpad\Accounts\Passkeys\Passkeys;
use Launchpad\Models\AccountEvent;
use Throwable;

/** Adding and removing passkeys on the Security page */
class PasskeyController
{
    public function options(Request $request, Passkeys $passkeys): JsonResponse
    {
        return response()->json(json_decode($passkeys->registrationOptions($request->user())));
    }

    public function store(Request $request, Passkeys $passkeys): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'credential' => ['required', 'string', 'json', 'max:20000'],
        ]);

        try {
            $passkeys->store($request->user(), $data['credential'], $data['name'], $request->getHost());
        } catch (Throwable) {
            return response()->json(['message' => __('That passkey could not be added. Try again.')], 422);
        }

        AccountEvent::record($request->user(), 'sign_in_method_added');
        session()->flash('status', __('Passkey added.'));

        return response()->json(['redirect' => route('account.security')]);
    }

    public function destroy(Request $request, int $passkey): RedirectResponse
    {
        $request->user()->passkeys()->whereKey($passkey)->firstOrFail()->delete();
        AccountEvent::record($request->user(), 'sign_in_method_removed');

        return redirect()->route('account.security')->with('status', __('Passkey removed.'));
    }
}
