<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request, PaymentManager $paymentManager): Response
    {
        $club = Club::first(); // Currently active tenant/club
        $provider = $club->settings['payment_provider'] ?? 'stripe';
        $gateway = $paymentManager->driver($provider);

        return Inertia::render('Admin/Billing/Index', [
            'club' => $club,
            'activeProvider' => $provider,
            'stripeConfigured' => ! empty(config('cashier.key')) && ! empty(config('cashier.secret')),
            'paddleConfigured' => ! empty(config('cashier.vendor_id')) || ! empty(config('cashier.api_key')),
            'plans' => [
                [
                    'id' => 'plan_basic',
                    'name' => 'Basic Tier',
                    'price' => '$29/mo',
                    'stripe_price_id' => env('STRIPE_PRICE_BASIC', 'price_stripe_basic_demo'),
                    'paddle_price_id' => env('PADDLE_PRICE_BASIC', 'pri_paddle_basic_demo'),
                    'features' => ['Up to 100 Members', 'Basic Event Management', 'Standard CMS'],
                ],
                [
                    'id' => 'plan_pro',
                    'name' => 'Pro Club Tier',
                    'price' => '$79/mo',
                    'stripe_price_id' => env('STRIPE_PRICE_PRO', 'price_stripe_pro_demo'),
                    'paddle_price_id' => env('PADDLE_PRICE_PRO', 'pri_paddle_pro_demo'),
                    'features' => ['Unlimited Members', '3-Course Dining & Dietary', 'Custom Domain CMS', 'Newsletter & SMS'],
                ],
            ],
            'flashStatus' => $request->query('status'),
        ]);
    }

    public function updateProvider(Request $request): RedirectResponse
    {
        $request->validate([
            'provider' => 'required|in:stripe,paddle',
        ]);

        $club = Club::first();
        $settings = $club->settings ?? [];
        $settings['payment_provider'] = $request->provider;
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Active payment provider updated to '.strtoupper($request->provider));
    }

    public function checkout(Request $request, PaymentManager $paymentManager): RedirectResponse
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $club = Club::first();
        $gateway = $paymentManager->forClub($club);

        try {
            $checkoutUrl = $gateway->createCheckoutSession($club, $request->price_id);

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Checkout initialization notice: Gateway API keys pending. '.$e->getMessage());
        }
    }

    public function portal(PaymentManager $paymentManager): RedirectResponse
    {
        $club = Club::first();
        $gateway = $paymentManager->forClub($club);

        try {
            $portalUrl = $gateway->createCustomerPortalSession($club, route('billing.index'));

            return redirect()->away($portalUrl);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Customer Portal unavailable until active subscription exists.');
        }
    }
}
