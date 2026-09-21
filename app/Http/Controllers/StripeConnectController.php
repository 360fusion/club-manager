<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPlatformAccount;
use App\Services\Payment\StripeConnectGateway;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A lodge connects a Stripe account so card payments go to it and the platform's commission is taken: either an
 * existing account ("Connect with Stripe", like signing in with Stripe) or a new one Stripe sets up for it.
 */
class StripeConnectController extends Controller
{
    public function __construct(private StripeConnectGateway $stripe) {}

    /**
     * Send the officer to Stripe to approve connecting an account they already have.
     */
    public function connect(Request $request, string $clubSlug): Response
    {
        $club = $this->club($clubSlug);
        $this->assertAvailable();

        $state = Str::random(40);
        $request->session()->put('stripe_connect', ['state' => $state, 'club' => $club->id]);

        return Inertia::location($this->stripe->authorizeUrl($state, route('stripe.connect.callback')));
    }

    /**
     * Where Stripe sends the officer back after approving. One fixed address for every lodge, so the lodge and the
     * person are checked against what was stored when they left.
     */
    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('stripe_connect');
        $club = $pending ? Club::find($pending['club']) : null;

        if (! $club || ! hash_equals((string) $pending['state'], (string) $request->query('state')) || ! ClubAccess::can($request->user(), $club, 'manage_billing')) {
            abort(403, 'This connection request is not valid. Please start again from Payment options.');
        }

        $back = route('admin.payment_options.index', ['clubSlug' => $club->slug]);

        if ($request->query('error') || ! $request->query('code')) {
            return redirect($back)->withErrors(['stripe' => 'The Stripe connection was cancelled.']);
        }

        $this->assertAvailable();
        $accountId = $this->stripe->accountIdForCode((string) $request->query('code'));

        $this->store($club, ClubPlatformAccount::STANDARD, $accountId);

        return redirect($back)->with('success', 'Stripe account connected.');
    }

    /**
     * Have Stripe set up a new account for a lodge that has none, then send the officer to Stripe to finish it.
     */
    public function express(Request $request, string $clubSlug): Response
    {
        $club = $this->club($clubSlug);
        $this->assertAvailable();

        $existing = $club->platformAccount;

        if ($existing && $existing->disconnected_at === null && $existing->type === ClubPlatformAccount::STANDARD) {
            throw ValidationException::withMessages(['stripe' => 'An existing Stripe account is already connected.']);
        }

        if (! $existing || $existing->disconnected_at !== null) {
            $country = $this->country($club);
            $accountId = $this->stripe->createExpressAccount($country, $club->settings['contact_email'] ?? $request->user()->email, $club->name);
            $existing = $this->store($club, ClubPlatformAccount::EXPRESS, $accountId, $country);
        }

        return Inertia::location($this->stripe->onboardingUrl(
            $existing->stripe_account_id,
            route('admin.payment_options.stripe.refresh', ['clubSlug' => $club->slug]),
            route('admin.payment_options.stripe.return', ['clubSlug' => $club->slug]),
        ));
    }

    /**
     * Stripe's setup link expired: start a fresh one.
     */
    public function refresh(string $clubSlug): Response
    {
        $club = $this->club($clubSlug);
        $account = $this->activeAccount($club);

        return Inertia::location($this->stripe->onboardingUrl(
            $account->stripe_account_id,
            route('admin.payment_options.stripe.refresh', ['clubSlug' => $club->slug]),
            route('admin.payment_options.stripe.return', ['clubSlug' => $club->slug]),
        ));
    }

    /**
     * Back from Stripe's setup page: read where the account stands.
     */
    public function done(string $clubSlug): RedirectResponse
    {
        $club = $this->club($clubSlug);
        $account = $this->activeAccount($club);
        $account->update($this->stripe->status($account->stripe_account_id));

        return redirect()->route('admin.payment_options.index', ['clubSlug' => $club->slug])->with('success', $account->charges_enabled ? 'Stripe is ready to take card payments.' : 'Stripe still needs a few details before it can take payments.');
    }

    /**
     * Agree to the platform's payment terms and fee, which is required before card payments are taken through the account.
     */
    public function acceptTerms(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->club($clubSlug);
        $account = $this->activeAccount($club);

        $request->validate(['agree' => 'accepted']);
        $account->update(['terms_accepted_at' => now(), 'terms_accepted_by' => $request->user()->id]);

        return redirect()->back()->with('success', 'Thank you. Terms accepted.');
    }

    public function disconnect(string $clubSlug): RedirectResponse
    {
        $club = $this->club($clubSlug);
        $account = $this->activeAccount($club);

        if ($account->type === ClubPlatformAccount::STANDARD) {
            $this->stripe->disconnect($account->stripe_account_id);
        }

        $account->update(['disconnected_at' => now(), 'charges_enabled' => false, 'payouts_enabled' => false, 'terms_accepted_at' => null]);

        return redirect()->back()->with('success', 'Stripe disconnected. Card payments through the platform are switched off.');
    }

    private function club(string $clubSlug): Club
    {
        return Club::where('slug', $clubSlug)->with('platformAccount')->firstOrFail();
    }

    private function activeAccount(Club $club): ClubPlatformAccount
    {
        $account = $club->platformAccount;
        abort_unless($account && $account->disconnected_at === null, 404);

        return $account;
    }

    private function assertAvailable(): void
    {
        if (! config('platform_payments.enabled') || empty(config('platform_payments.stripe_secret'))) {
            throw ValidationException::withMessages(['stripe' => 'Connecting Stripe is not available yet.']);
        }
    }

    /**
     * The two-letter country Stripe needs for a new account: the club's own setting, else its currency's country, else the UK.
     */
    private function country(Club $club): string
    {
        $code = strtoupper((string) ($club->settings['country_code'] ?? ''));

        if (strlen($code) === 2) {
            return $code;
        }

        return match ($club->currencyCode()) {
            'USD' => 'US', 'AUD' => 'AU', 'NZD' => 'NZ', 'CAD' => 'CA', 'CHF' => 'CH', 'BRL' => 'BR', 'EUR' => 'IE',
            default => 'GB',
        };
    }

    private function store(Club $club, string $type, string $accountId, ?string $country = null): ClubPlatformAccount
    {
        $status = ['country' => $country];

        try {
            $status = $this->stripe->status($accountId) + ['country' => $country];
        } catch (ValidationException) {
            // The status webhook fills these in shortly if Stripe could not be read now.
        }

        return ClubPlatformAccount::updateOrCreate(['club_id' => $club->id], [
            'type' => $type,
            'stripe_account_id' => $accountId,
            'disconnected_at' => null,
            'terms_accepted_at' => null,
            'terms_accepted_by' => null,
        ] + $status);
    }
}
