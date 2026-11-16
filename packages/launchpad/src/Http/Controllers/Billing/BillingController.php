<?php

namespace Launchpad\Http\Controllers\Billing;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Billing\Entitlements;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\Money;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\PlanChange;
use Launchpad\Http\Ui;
use Launchpad\Metering\Usage;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/** The billing page of a workspace: its plan, card and invoices, and the ways to change them. Stripe's portal does the changing. */
class BillingController extends Controller
{
    public function show(CurrentWorkspace $current, Entitlements $entitlements, Gateway $gateway, PlanCatalog $catalog, Usage $usage): View|Responsable
    {
        $workspace = $current->workspace;
        [$plan, $period] = $entitlements->resolve($workspace);
        $subscription = $workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'));

        $overview = $gateway->overview($workspace);
        $paymentProblem = $subscription && ! $subscription->valid() && ! $subscription->ended();
        $meters = $usage->summary($workspace);

        return Ui::render('billing.show', [
            'workspace' => $workspace,
            'plan' => $plan,
            'period' => $period,
            'subscription' => $subscription,
            // a subscription that is not valid but exists is one that failed to pay (or has ended): the page says so
            'paymentProblem' => $paymentProblem,
            'trialEndsAt' => $subscription?->onTrial() ? $subscription->trial_ends_at->isoFormat('ll') : null,
            'overview' => $overview,
            'plans' => $catalog->all(),
            'catalog' => $catalog,
            'seats' => $entitlements->limit($workspace, 'seats'),
            'meters' => $meters,
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug, 'hasStripe' => $workspace->hasStripeId()],
            'plan' => ['key' => $plan->key, 'name' => __($plan->name), 'perSeat' => $plan->perSeat],
            'period' => $period,
            'seats' => $entitlements->limit($workspace, 'seats'),
            'paymentProblem' => $paymentProblem,
            'overview' => ['card' => $overview['card'], 'invoices' => $overview['invoices'], 'renewsAt' => $overview['renews_at']?->isoFormat('ll'), 'endsAt' => $overview['ends_at']?->isoFormat('ll'),
                'discount' => ($d = $overview['discount'] ?? null) ? ['description' => $d['description'], 'code' => $d['code'], 'endsAt' => $d['ends_at']?->isoFormat('ll')] : null],
            'canCancel' => $period !== null && ! $overview['ends_at'],
            'meters' => $meters,
            'changeOptions' => collect($catalog->all())->reject(fn ($o) => $o->isFree())->flatMap(fn ($o) => collect($o->periods())
                ->filter(fn ($p) => $catalog->onSale($o, $p) && ! ($o->key === $plan->key && $p === $period))
                ->map(fn ($p) => ['plan' => $o->key, 'name' => __($o->name), 'period' => $p, 'price' => Money::format($o->amount($p))]))->values(),
        ]);
    }

    public function portal(CurrentWorkspace $current, Gateway $gateway): Response
    {
        abort_unless($current->workspace->hasStripeId(), 404);

        return Ui::away($gateway->portalUrl($current->workspace, route('workspace.billing', $current->workspace->slug)));
    }

    /** What changing to this plan would do and when, before anything is changed */
    public function review(CurrentWorkspace $current, string $slug, string $plan, string $frequency, Entitlements $entitlements, PlanCatalog $catalog): View|Responsable|RedirectResponse
    {
        $target = $catalog->find($plan);
        abort_unless($target && $catalog->onSale($target, $frequency), 404);

        [$from, $fromPeriod] = $entitlements->resolve($current->workspace);

        if ($from->key === $target->key && $fromPeriod === $frequency) {
            return redirect()->route('workspace.billing', $slug);
        }

        $timing = $fromPeriod === null ? PlanChange::NOW : PlanChange::timing($from, $fromPeriod, $target, $frequency);

        return Ui::render('billing.change', [
            'workspace' => $current->workspace,
            'from' => $from,
            'fromPeriod' => $fromPeriod,
            'to' => $target,
            'toPeriod' => $frequency,
            'timing' => $timing,
            'hasSubscription' => $fromPeriod !== null,
        ], fn () => [
            'workspace' => ['name' => $current->workspace->name, 'slug' => $current->workspace->slug],
            'from' => ['name' => __($from->name)],
            'to' => ['key' => $target->key, 'name' => __($target->name)],
            'toPeriod' => $frequency,
            'timing' => $timing,
            'hasSubscription' => $fromPeriod !== null,
        ]);
    }

    /** Hands over to Stripe's own "confirm this change" page: the app never changes a price itself */
    public function change(Request $request, CurrentWorkspace $current, string $slug, string $plan, string $frequency, Gateway $gateway, PlanCatalog $catalog, Entitlements $entitlements): Response
    {
        $target = $catalog->find($plan);
        abort_unless($target && $catalog->onSale($target, $frequency), 404);

        if (! $current->workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'))?->valid()) {
            return redirect()->route('checkout.start', [$plan, $frequency, 'workspace' => $slug]);
        }

        return Ui::away($gateway->portalUrl($current->workspace, route('workspace.billing', $slug), [
            'type' => 'subscription_update_confirm',
            'price' => $target->priceId($frequency),
            'quantity' => $target->perSeat ? max($target->minSeats, $current->workspace->memberships()->count()) : 1,
        ]));
    }

    public function cancel(CurrentWorkspace $current, string $slug, Gateway $gateway): Response
    {
        abort_unless($current->workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'))?->valid(), 404);

        return Ui::away($gateway->portalUrl($current->workspace, route('workspace.billing', $slug), ['type' => 'subscription_cancel']));
    }
}
