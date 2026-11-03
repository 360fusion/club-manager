<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\DeviceSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Signing one other device out; the password is asked for as it is for signing out all of them */
class DeviceController extends Controller
{
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        DeviceSessions::revoke($request->user(), $id, $request->session()->getId());

        return back()->with('success', 'That device has been signed out.');
    }
}
