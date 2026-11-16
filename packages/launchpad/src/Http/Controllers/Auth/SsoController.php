<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Http\Ui;
use Launchpad\Models\Membership;
use Launchpad\Models\SsoConnection;
use Launchpad\Sso\Oidc;
use Launchpad\Sso\Saml;
use Launchpad\Workspaces\Role;
use OneLogin\Saml2\Settings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing in with a company account: the person gives their email address, is sent to their team's provider, and comes back with a code that is
 * traded for a checked ID token. Someone new is made (verified) and put in the team with its default role; someone known is signed in and joins it.
 */
class SsoController extends Controller
{
    /** How long a started sign-in may take, in seconds */
    private const TIMEOUT = 600;

    public function create(Request $request): View|Responsable
    {
        $email = (string) $request->old('email', $request->query('email', ''));

        return Ui::render('auth.sso', ['email' => $email], fn () => ['email' => $email]);
    }

    public function start(Request $request): RedirectResponse|Response
    {
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];
        $connection = SsoConnection::forEmail($email);

        if ($connection === null) {
            return back()->withInput()->withErrors(['email' => __('Single sign-on is not set up for that address. Sign in with your password instead.')]);
        }

        if ($connection->protocol === 'saml') {
            return Saml::available() ? Ui::away(Saml::start($connection, $email)) : back()->withInput()->withErrors(['email' => __('Your team\'s sign-in provider could not be reached. Please try again later.')]);
        }

        $pending = ['connection' => $connection->id, 'state' => Str::random(40), 'nonce' => Str::random(40), 'verifier' => Oidc::newVerifier(), 'at' => now()->timestamp];

        try {
            $url = Oidc::authorizationUrl($connection, $pending['state'], $pending['nonce'], $pending['verifier'], route('sso.callback'), $email);
        } catch (InvalidArgumentException $e) {
            Log::warning('Single sign-on could not start', ['connection' => $connection->id, 'error' => $e->getMessage()]);

            return back()->withInput()->withErrors(['email' => __('Your team\'s sign-in provider could not be reached. Please try again later.')]);
        }

        $request->session()->put('launchpad.sso', $pending);

        return Ui::away($url);
    }

    public function callback(Request $request, AccountRegistration $accounts): RedirectResponse
    {
        // used once, whatever happens next
        $pending = $request->session()->pull('launchpad.sso');

        if (! is_array($pending) || ! hash_equals((string) $pending['state'], (string) $request->query('state', '')) || now()->timestamp - (int) $pending['at'] > self::TIMEOUT) {
            return $this->refuse(__('That sign-in had expired or did not match. Please start again.'));
        }

        $connection = SsoConnection::whereKey($pending['connection'])->whereNotNull('domain_verified_at')->first();

        if ($connection === null || $request->query('error') || ! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->refuse(__('Signing in with your company account did not work, or was cancelled. Please try again.'));
        }

        try {
            $claims = Oidc::exchange($connection, (string) $request->query('code'), (string) $pending['verifier'], (string) $pending['nonce'], route('sso.callback'));
        } catch (InvalidArgumentException $e) {
            Log::warning('Single sign-on refused', ['connection' => $connection->id, 'error' => $e->getMessage()]);

            return $this->refuse(__('Signing in with your company account did not work, or was cancelled. Please try again.'));
        }

        return $this->signIn($request, $connection, $claims['email'], $claims['name'] ?? null, $accounts);
    }

    /** The SAML provider's answer, posted back from its site (no CSRF token can come with it: what it carries is checked instead) */
    public function samlAcs(Request $request, SsoConnection $connection, AccountRegistration $accounts): RedirectResponse
    {
        abort_unless($connection->protocol === 'saml' && $connection->domain_verified_at !== null && Saml::available(), 404);

        try {
            $person = Saml::finish($request, $connection);
        } catch (InvalidArgumentException $e) {
            Log::warning('Single sign-on refused', ['connection' => $connection->id, 'error' => $e->getMessage()]);

            return $this->refuse(__('Signing in with your company account did not work, or was cancelled. Please try again.'));
        }

        return $this->signIn($request, $connection, $person['email'], $person['name'], $accounts);
    }

    /** What the provider needs to know about this site, as SAML metadata */
    public function samlMetadata(SsoConnection $connection): Response
    {
        abort_unless($connection->protocol === 'saml' && Saml::available(), 404);

        $xml = (new Settings(Saml::settings($connection), true))->getSPMetadata();

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    /** After the provider vouched for $email: the same for OpenID Connect and SAML */
    private function signIn(Request $request, SsoConnection $connection, string $email, ?string $name, AccountRegistration $accounts): RedirectResponse
    {
        $user = AccountRegistration::findByEmail($email);

        if ($user && method_exists($user, 'isClosed') && $user->isClosed()) {
            return $this->refuse(__('This account has been closed.'));
        }

        if ($user === null) {
            $user = $accounts->register(['name' => (string) $name, 'email' => $email, 'password' => Str::random(40)], emailVerified: true, passwordChosen: false);
        } elseif (! $user->hasVerifiedEmail()) {
            // the team's own provider vouches for this address: whatever someone else set up on it unproved is taken back, not handed over
            $accounts->takeBack($user);
        }

        $workspace = $connection->workspace;
        Membership::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()], ['role' => Role::tryFrom($connection->default_role) ?? Role::Member]);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        if (app(TwoFactor::class)->isOn($user)) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('launchpad.routes.after_login'));
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
