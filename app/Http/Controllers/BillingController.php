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
    public function index(Request $request, string $clubSlug): RedirectResponse
    {
        return redirect()->route('admin.memberships.index', [
            'clubSlug' => $clubSlug,
            'status' => $request->query('status'),
        ]);
    }

    public function updateProvider(Request $request, string $clubSlug): RedirectResponse
    {
        $request->validate([
            'provider' => 'required|in:stripe,paddle',
        ]);

        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $settings = $club->settings ?? [];
        $settings['payment_provider'] = $request->provider;
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Active payment provider updated to '.strtoupper($request->provider));
    }

    public function checkout(Request $request, string $clubSlug, PaymentManager $paymentManager): RedirectResponse
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $gateway = $paymentManager->forClub($club);

        try {
            $checkoutUrl = $gateway->createCheckoutSession($club, $request->price_id);

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Checkout initialization notice: Gateway API keys pending. '.$e->getMessage());
        }
    }

    public function portal(Request $request, string $clubSlug, PaymentManager $paymentManager): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $gateway = $paymentManager->forClub($club);

        try {
            $portalUrl = $gateway->createCustomerPortalSession($club, route('admin.memberships.index', ['clubSlug' => $club->slug]));

            return redirect()->away($portalUrl);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Customer Portal unavailable until active subscription exists.');
        }
    }
}
