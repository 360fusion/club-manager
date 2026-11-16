<?php

namespace Launchpad\Http\Controllers\Billing;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Launchpad\Billing\Entitlements;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\PromoCode;
use Launchpad\Http\Ui;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\Workspaces;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    /** Sends the person to Stripe's checkout for a plan, for the workspace they name (?workspace=slug) or else their own */
    public function start(Request $request, string $plan, string $frequency, PlanCatalog $catalog, Gateway $gateway, Entitlements $entitlements, Workspaces $workspaces): Response
    {
        PromoCode::remember($request);
        $plan = $catalog->find($plan);
        $period = $plan ? $catalog->period($plan, $frequency) : null;

        if (! $plan || $period !== $frequency || ! $catalog->onSale($plan, $period)) {
            return redirect()->route('pricing')->withErrors(['plan' => __('That plan is not available to buy right now.')]);
        }

        $workspace = $this->workspace($request, $workspaces);
        $member = Membership::where(['workspace_id' => $workspace->id, 'user_id' => $request->user()->getKey()])->first();
        abort_unless($member?->can(Ability::ManageBilling), 403);

        // a second subscription would bill twice: changing plan goes through the review page
        if ($workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'))?->valid()) {
            // without Launchpad's billing pages (launchpad.workspaces.pages off) the app changes plans its own way
            abort_unless(Route::has('workspace.billing.change'), 409, 'This team already has a plan.');

            return redirect()->route('workspace.billing.change', [$workspace->slug, $plan->key, $period]);
        }

        $quantity = $plan->perSeat ? max($plan->minSeats, $workspace->memberships()->count()) : 1;

        return Ui::away($gateway->checkoutUrl(
            $workspace,
            $plan->priceId($period),
            $quantity,
            route('checkout.success').'?workspace='.$workspace->slug,
            route('checkout.cancelled'),
            // one trial for a workspace: its first subscription only
            $plan->trialDays > 0 && ! $workspace->subscriptions()->exists() ? $plan->trialDays : 0,
            PromoCode::pending(),
        ));
    }

    public function success(Request $request): View|Responsable
    {
        PromoCode::forget();

        $workspace = Workspace::where('slug', $request->query('workspace'))
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $request->user()->getKey()))->first();

        return Ui::render('billing.checkout-success', ['workspace' => $workspace], fn () => ['workspaceSlug' => $workspace?->slug]);
    }

    public function cancelled(): View|Responsable
    {
        return Ui::render('billing.checkout-cancelled');
    }

    private function workspace(Request $request, Workspaces $workspaces): Workspace
    {
        if ($slug = $request->query('workspace')) {
            return Workspace::where('slug', $slug)->whereHas('memberships', fn ($query) => $query->where('user_id', $request->user()->getKey()))->firstOrFail();
        }

        return $request->user()->currentWorkspace() ?? $workspaces->createPersonal($request->user());
    }
}
