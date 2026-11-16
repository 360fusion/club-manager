<?php

namespace Launchpad\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\SocialProviders;
use Launchpad\Accounts\TwoFactor;
use Launchpad\Accounts\Waitlist;
use Launchpad\Models\AccountEvent;
use Launchpad\Models\SocialAccount;
use Launchpad\Models\SsoConnection;
use Symfony\Component\HttpFoundation\RedirectResponse as SocialRedirect;

/**
 * Sign in with Google, Facebook or Microsoft (the routes only take those three names).
 *
 * An identity is who it is by (provider, provider_id), kept in social_accounts, and never by the email it carries. An existing account is
 * only signed in to by an identity that is not linked to it yet when the provider vouches for the address, and only Google does that (its
 * `email_verified`): Facebook says nothing, and Microsoft's "email" is the userPrincipalName, a sign-in name that can differ from the real
 * address and be given to somebody else later. Everyone else gets an account of their own that has to confirm its address by mail.
 */
class SocialLoginController extends Controller
{
    public function redirect(string $provider): SocialRedirect|RedirectResponse
    {
        if (! SocialProviders::configured($provider)) {
            return $this->refuse(__('Signing in with :provider is not available right now. Please use your email address instead.', ['provider' => SocialProviders::name($provider)]));
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, AccountRegistration $accounts): RedirectResponse
    {
        abort_unless(SocialProviders::configured($provider), 404);

        $name = SocialProviders::name($provider);

        // someone already signed in who asked to link this provider from the Security page
        if (Auth::check()) {
            return $this->connect($provider, $name);
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning('Social sign-in failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->refuse(__('Signing in with :provider did not work, or was cancelled. Please try again.', ['provider' => $name]));
        }

        $providerId = (string) $social->getId();
        $linked = SocialAccount::where(['provider' => $provider, 'provider_id' => $providerId])->with('user')->first();

        if ($linked?->user) {
            if ($this->singleSignOnRequired($linked->user->email)) {
                return $this->refuse(__('Your team signs in with single sign-on. Use “Sign in with single sign-on” instead.'));
            }

            return $this->signIn($linked->user, $name);
        }

        $email = $this->email($provider, $social);

        if ($this->singleSignOnRequired($email)) {
            return $this->refuse(__('Your team signs in with single sign-on. Use “Sign in with single sign-on” instead.'));
        }

        if ($email === '') {
            return $this->refuse(__(':provider did not share an email address, so an account cannot be made from it. Please sign up with your email address instead.', ['provider' => $name]));
        }

        $vouched = $this->providerVouchesForTheAddress($provider, $social);
        $existing = AccountRegistration::findByEmail($email);

        if ($existing === null) {
            if (AccountRegistration::isFromBlockedCountry($email)) {
                return $this->refuse(__((string) config('launchpad.signup.blocked_country_message')));
            }

            if (AccountRegistration::isThrowaway($email)) {
                return $this->refuse(__('Please use an address you keep: temporary email services are not accepted.'));
            }

            if (! Waitlist::allows($email)) {
                return $this->refuse(Waitlist::message());
            }

            $user = $accounts->register([
                'name' => $social->getName() ?: $social->getNickname() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
            ], emailVerified: $vouched, passwordChosen: false);

            $this->link($user, $provider, $providerId);

            return $this->signIn($user, $name);
        }

        if (method_exists($existing, 'isClosed') && $existing->isClosed()) {
            return $this->refuse(__('This account has been closed.'));
        }

        if (! $vouched) {
            return $this->refuse(__('There is already an account with the email address :provider gave us. Please sign in with your password, or with Google.', ['provider' => $name]));
        }

        if (! $existing->hasVerifiedEmail()) {
            $accounts->takeBack($existing);
        }

        $this->link($existing, $provider, $providerId);

        return $this->signIn($existing, $name);
    }

    private function connect(string $provider, string $name): RedirectResponse
    {
        $asked = session()->pull('connect_provider') === $provider;
        $back = redirect()->route('account.security');

        if (! $asked) {
            return $back;
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning('Linking a sign-in method failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $back->withErrors(['connections' => __('Linking :provider did not work, or was cancelled.', ['provider' => $name])]);
        }

        $providerId = (string) $social->getId();
        $held = SocialAccount::where(['provider' => $provider, 'provider_id' => $providerId])->first();

        if ($held && $held->user_id !== Auth::id()) {
            return $back->withErrors(['connections' => __('That :provider account is already used by another account here.', ['provider' => $name])]);
        }

        $this->link(Auth::user(), $provider, $providerId);
        AccountEvent::record(Auth::user(), 'sign_in_method_added');

        return $back->with('status', __(':provider is now a way to sign in.', ['provider' => $name]));
    }

    /** The address the provider gave, as an account keeps it ('' when it gave none) */
    private function email(string $provider, SocialUser $social): string
    {
        $address = $provider === 'azure' ? ($social->getRaw()['mail'] ?? $social->getEmail()) : $social->getEmail();

        return AccountRegistration::normalizeEmail(is_string($address) ? $address : '');
    }

    private function providerVouchesForTheAddress(string $provider, SocialUser $social): bool
    {
        return $provider === 'google'
            && filter_var($social->getRaw()['email_verified'] ?? $social->getRaw()['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function link($user, string $provider, string $providerId): void
    {
        SocialAccount::firstOrCreate(['provider' => $provider, 'provider_id' => $providerId], ['user_id' => $user->getKey()]);
    }

    /**
     * Takes an account back from whoever made it: a password nobody can know, no sign-in kept, no two-factor secret (an attacker can turn
     * it on for an account that was never verified, and it would lock the owner out), none of the identities they linked (they would go on
     * signing in with no password), and a verified address. The identity that is vouching for the address right now is linked after this.
     */
    /** Signs in, or hands over to the two-factor step the account has turned on, as the password login does */
    private function signIn($user, string $providerName): RedirectResponse
    {
        if (method_exists($user, 'isClosed') && $user->isClosed()) {
            return $this->refuse(__('This account has been closed.'));
        }

        if (app(TwoFactor::class)->isOn($user)) {
            session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->intended(config('launchpad.routes.after_login'));
    }

    /** The address is on a domain whose team requires its own single sign-on */
    private function singleSignOnRequired(?string $email): bool
    {
        return config('launchpad.modules.sso') && (bool) SsoConnection::forEmail($email)?->enforce;
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
