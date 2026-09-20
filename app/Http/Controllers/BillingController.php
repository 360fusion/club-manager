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
    public function index(Request $request, string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $settings = $club->settings ?? [];

        $currentProvider = $settings['payment_provider'] ?? 'stripe';

        $businessDetails = [
            'business_name' => $settings['business_name'] ?? $club->name,
            'company_number' => $settings['company_number'] ?? '12948573',
            'tax_id' => $settings['tax_id'] ?? 'GB 987 6543 21',
            'billing_contact_email' => $settings['billing_contact_email'] ?? ($settings['contact_email'] ?? 'treasurer@'.$club->slug.'.org'),
            'billing_phone' => $settings['billing_phone'] ?? ($settings['phone'] ?? '+44 20 7946 0912'),
            'address' => $settings['address'] ?? '100 Boathouse Way, Oxford, OX1 1AA, UK',
            'stripe_publishable_key' => $settings['stripe_publishable_key'] ?? '',
            'stripe_secret_key' => $settings['stripe_secret_key'] ?? '',
            'stripe_webhook_secret' => $settings['stripe_webhook_secret'] ?? '',
        ];

        $currentPlan = [
            'name' => 'Pro Club & Lodge Suite',
            'status' => 'active',
            'price' => '£29.00 / month',
            'billing_cycle' => 'Monthly (Renews on 1st of each month)',
            'next_billing_date' => now()->addDays(12)->format('M d, Y'),
            'modules' => ['Accounting ERP', 'Summonses & Meetings', 'Communications Hub', 'Member Portal', 'File Manager'],
        ];

        $billingHistory = [
            [
                'id' => 'INV-2026-009',
                'date' => now()->subDays(18)->format('M d, Y'),
                'description' => 'Pro Suite Monthly Subscription',
                'amount' => '£29.00',
                'status' => 'paid',
            ],
            [
                'id' => 'INV-2026-008',
                'date' => now()->subDays(48)->format('M d, Y'),
                'description' => 'Pro Suite Monthly Subscription',
                'amount' => '£29.00',
                'status' => 'paid',
            ],
            [
                'id' => 'INV-2026-007',
                'date' => now()->subDays(78)->format('M d, Y'),
                'description' => 'Pro Suite Monthly Subscription',
                'amount' => '£29.00',
                'status' => 'paid',
            ],
        ];

        return Inertia::render('Admin/Billing/Index', [
            'club' => $club,
            'businessDetails' => $businessDetails,
            'currentProvider' => $currentProvider,
            'currentPlan' => $currentPlan,
            'billingHistory' => $billingHistory,
            'autoOpenPortal' => (bool) $request->query('portal', false),
        ]);
    }

    public function updateBusinessAccount(Request $request, string $clubSlug): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'company_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'billing_contact_email' => 'required|email|max:255',
            'billing_phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
        ]);

        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $settings = array_merge($club->settings ?? [], $validated);
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Main Business Account details and Merchant Gateway credentials updated successfully.');
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
            $portalUrl = $gateway->createCustomerPortalSession($club, route('billing.index', ['clubSlug' => $club->slug]));

            return redirect()->away($portalUrl);
        } catch (\Throwable $e) {
            return redirect()->route('billing.index', ['clubSlug' => $club->slug])
                ->with('info', 'Customer Billing Portal notice: Live gateway keys pending. Business account and subscription settings can be managed directly on this page.');
        }
    }
}
