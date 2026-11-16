<?php

namespace Launchpad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Launchpad\Admin\Impersonation;
use Launchpad\Launchpad;

/** Staff signing in as a customer, and back out */
class ImpersonationController extends Controller
{
    /** The link the admin's button sends staff to: signed, and good for a minute */
    public static function startUrl(object $user): string
    {
        return URL::temporarySignedRoute('impersonate.start', now()->addMinute(), ['user' => $user->getKey()]);
    }

    public function start(Request $request, string $user): RedirectResponse
    {
        $staff = Auth::guard('admin')->user();
        $target = Launchpad::userModel()::findOrFail($user);

        abort_unless(Impersonation::allowed($staff, $target), 403);

        Impersonation::start($staff, $target);

        return redirect(config('launchpad.routes.after_login'));
    }

    public function stop(Request $request): RedirectResponse
    {
        $staff = Impersonation::stop();

        return $staff ? redirect(url(config('launchpad.admin.path', 'hq'))) : redirect('/');
    }
}
