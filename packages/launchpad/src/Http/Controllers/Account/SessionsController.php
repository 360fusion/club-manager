<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Launchpad\Accounts\DeviceSessions;
use Launchpad\Models\AccountEvent;

/** Active devices on the Security page: sign out of all the others */
class SessionsController extends Controller
{
    public function destroyOthers(Request $request): RedirectResponse
    {
        abort_unless(DeviceSessions::available(), 404);

        $ended = DeviceSessions::endOthers($request->user(), $request->session()->getId());
        AccountEvent::record($request->user(), 'sessions_ended');

        return back()->with('status', $ended ? __('Signed out of your other devices.') : __('There were no other devices signed in.'));
    }
}
