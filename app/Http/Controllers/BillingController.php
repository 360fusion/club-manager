<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\CheckoutRequest;
use App\Http\Requests\Billing\UpdateBusinessAccountRequest;
use App\Http\Requests\Billing\UpdateProviderRequest;
use App\Models\Club;
use App\Services\Payment\PaymentManager;
use App\Support\PlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;

class BillingController extends Controller
{
    /**
     * Gateway secrets are stored encrypted and never sent back to the browser.
     */
    private const SECRET_KEYS = ['stripe_secret_key', 'stripe_webhook_secret'];

    public function index(Request $request, string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
        $settings = $club->settings ?? [];

        $currentProvider = $settings['payment_provider'] ?? 'stripe';

        $businessDetails = [
            'business_name' => $settings['business_name'] ?? $club->name,
            'company_number' => $settings['company_number'] ?? '',
            'tax_id' => $settings['tax_id'] ?? '',
            'billing_contact_email' => $settings['billing_contact_email'] ?? ($settings['contact_email'] ?? 'treasurer@'.$club->slug.'.org'),
            'billing_phone' => $settings['billing_phone'] ?? ($settings['phone'] ?? ''),
            'address' => $settings['address'] ?? '',
            'stripe_publishable_key' => $settings['stripe_publishable_key'] ?? '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            'has_stripe_secret_key' => ! empty($settings['stripe_secret_key']),
            'has_stripe_webhook_secret' => ! empty($settings['stripe_webhook_secret']),
        ];

        $plan = PlanCatalog::forClub($club);
        $subscription = $club->subscription('default');

        $currentPlan = [
            'name' => $plan['name'],
            'status' => $subscription ? $this->statusOf($subscription) : 'free',
            'price' => null,
            'billing_cycle' => $plan['period'] ? ucfirst($plan['period']) : 'No paid plan',
            'next_billing_date' => $this->renewalDate($club, $subscription),
            'modules' => $plan['modules'],
        ];

        $billingHistory = $this->invoices($club);

        return Inertia::render('Admin/Billing/Index', [
            'club' => tap($club, fn (Club $c) => $c->settings = Arr::except($settings, self::SECRET_KEYS)),
            'businessDetails' => $businessDetails,
            'currentProvider' => $currentProvider,
            'currentPlan' => $currentPlan,
            'billingHistory' => $billingHistory,
            'autoOpenPortal' => (bool) $request->query('portal', false),
        ]);
    }

    public function updateBusinessAccount(UpdateBusinessAccountRequest $request, string $clubSlug): RedirectResponse
    {
        $validated = $request->validated();

        $club = $this->findClub($clubSlug);
        foreach (self::SECRET_KEYS as $key) {
            if (empty($validated[$key])) {
                unset($validated[$key]);
            } else {
                $validated[$key] = Crypt::encryptString($validated[$key]);
            }
        }

        $settings = array_merge($club->settings ?? [], $validated);
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Main Business Account details and Merchant Gateway credentials updated successfully.');
    }

    public function updateProvider(UpdateProviderRequest $request, string $clubSlug): RedirectResponse
    {

        $club = $this->findClub($clubSlug);
        $settings = $club->settings ?? [];
        $settings['payment_provider'] = $request->provider;
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Active payment provider updated to '.strtoupper($request->provider));
    }

    public function checkout(CheckoutRequest $request, string $clubSlug, PaymentManager $paymentManager): RedirectResponse
    {

        $club = $this->findClub($clubSlug);
        $gateway = $paymentManager->forClub($club);

        try {
            $checkoutUrl = $gateway->createCheckoutSession($club, $request->price_id);

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::warning('Platform checkout failed', ['club' => $club->id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Checkout could not be started. Check the Stripe keys and plan prices (php artisan billing:doctor), then try again.');
        }
    }

    public function portal(Request $request, string $clubSlug, PaymentManager $paymentManager): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $gateway = $paymentManager->forClub($club);

        try {
            $portalUrl = $gateway->createCustomerPortalSession($club, route('billing.index', ['clubSlug' => $club->slug]));

            return redirect()->away($portalUrl);
        } catch (\Throwable $e) {
            Log::warning('Billing portal failed', ['club' => $club->id, 'error' => $e->getMessage()]);

            return redirect()->route('billing.index', ['clubSlug' => $club->slug])
                ->with('info', 'The billing portal is not available yet: this club has no Stripe customer, or the Stripe keys are not set.');
        }
    }

    private function statusOf(Subscription $subscription): string
    {
        return match (true) {
            $subscription->onTrial() => 'trialing',
            $subscription->onGracePeriod() => 'cancelling',
            $subscription->active() => 'active',
            default => 'inactive',
        };
    }

    /** Stripe holds the renewal date, so ask it; a missing key or an outage leaves the line blank rather than breaking the page */
    private function renewalDate(Club $club, ?Subscription $subscription): ?string
    {
        if (! $subscription?->active() || $subscription->onGracePeriod() || blank(config('cashier.secret'))) {
            return $subscription?->ends_at?->format('j M Y');
        }

        try {
            return Carbon::createFromTimestamp($subscription->asStripeSubscription()->items->data[0]->current_period_end)->format('j M Y');
        } catch (\Throwable $e) {
            Log::info('Could not read the renewal date from Stripe', ['club' => $club->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return list<array{id: string, date: string, description: string, amount: string, status: string, url: ?string}> */
    private function invoices(Club $club): array
    {
        if (! $club->hasStripeId() || blank(config('cashier.secret'))) {
            return [];
        }

        try {
            return $club->stripeInvoices(true, ['limit' => 24])->map(fn ($invoice) => [
                'id' => $invoice->number ?? $invoice->id,
                'date' => $invoice->date()->format('j M Y'),
                'description' => $invoice->lines->data[0]->description ?? 'Subscription',
                'amount' => $invoice->total(),
                'status' => (string) $invoice->status,
                'url' => $invoice->hosted_invoice_url,
            ])->all();
        } catch (\Throwable $e) {
            Log::info('Could not read invoices from Stripe', ['club' => $club->id, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
