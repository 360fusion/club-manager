<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Launchpad\Accounts\SocialProviders;
use Launchpad\Models\AccountEvent;
use Launchpad\Models\SocialAccount;
use Symfony\Component\HttpFoundation\RedirectResponse as SocialRedirect;

/** Sign-in methods on the Security page: link another provider to the account, or take one off */
class ConnectionsController extends Controller
{
    public function connect(Request $request, string $provider): SocialRedirect|RedirectResponse
    {
        abort_unless(SocialProviders::configured($provider), 404);

        // the provider sends the person back to the one callback every sign-in uses, which links when it finds this
        $request->session()->put('connect_provider', $provider);

        return Socialite::driver($provider)->redirect();
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();
        $link = SocialAccount::where(['user_id' => $user->getKey(), 'provider' => $provider])->first();

        abort_unless($link, 404);

        // the last way in cannot be taken away: a person with no password of their own would be locked out
        if ($user->password_set_at === null && SocialAccount::where('user_id', $user->getKey())->count() < 2) {
            return back()->withErrors(['connections' => __('Set a password first: this is the only way you can sign in.')]);
        }

        $link->delete();
        AccountEvent::record($user, 'sign_in_method_removed');

        return back()->with('status', __(':provider is no longer a way to sign in.', ['provider' => SocialProviders::name($provider)]));
    }
}
