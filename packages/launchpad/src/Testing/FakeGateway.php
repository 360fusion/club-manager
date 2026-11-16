<?php

namespace Launchpad\Testing;

use Carbon\CarbonInterface;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\MeteredBilling;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\PromoCode;
use Launchpad\Models\Workspace;

/** Stands in for Stripe in tests: it records what was asked and answers with fixed addresses. Bind it with `$this->app->instance(Gateway::class, new FakeGateway)`. */
class FakeGateway implements Gateway
{
    /** @var list<array<string, mixed>> */
    public array $checkouts = [];

    /** @var list<array<string, mixed>> */
    public array $portals = [];

    /** @var array<string, mixed> */
    public array $overview = ['card' => null, 'invoices' => [], 'renews_at' => null, 'ends_at' => null];

    /** @var list<array{name: string, ok: bool, detail: string}> */
    public array $health = [];

    public function checkoutUrl(Workspace $workspace, string $priceId, int $quantity, string $successUrl, string $cancelUrl, int $trialDays = 0, ?string $promoCode = null): string
    {
        $metered = ($plan = app(PlanCatalog::class)->byPrice($priceId)[0] ?? null) ? MeteredBilling::prices($plan) : [];
        $this->checkouts[] = compact('priceId', 'quantity', 'successUrl', 'cancelUrl', 'trialDays') + ['workspace' => $workspace->id] + ($promoCode !== null ? ['promoCode' => $promoCode] : []) + ($metered ? ['metered' => $metered] : []);

        return 'https://checkout.stripe.test/session/'.$priceId;
    }

    public function portalUrl(Workspace $workspace, string $returnUrl, ?array $flow = null): string
    {
        $this->portals[] = ['workspace' => $workspace->id, 'returnUrl' => $returnUrl, 'flow' => $flow];

        return 'https://billing.stripe.test/portal/'.($flow['type'] ?? 'home');
    }

    public function overview(Workspace $workspace): array
    {
        return $this->overview;
    }

    /** @var list<array{workspace: int, event: string, quantity: int, identifier: string}> */
    public array $usageReports = [];

    public function reportUsage(Workspace $workspace, string $event, int $quantity, string $identifier): void
    {
        $this->usageReports[] = ['workspace' => $workspace->id, 'event' => $event, 'quantity' => $quantity, 'identifier' => $identifier];
    }

    /** @var list<int> workspaces whose subscriptions were ended */
    public array $cancelled = [];

    public function cancelSubscriptions(Workspace $workspace): void
    {
        $this->cancelled[] = $workspace->id;
    }

    /** @var list<array{id: string, code: string, discount: string, redeemed: int, max: ?int, expires_at: ?CarbonInterface, active: bool}> */
    public array $promotionCodes = [];

    public function promotionCodes(): array
    {
        return array_reverse($this->promotionCodes);
    }

    public function createPromotionCode(string $code, array $discount, ?int $maxRedemptions, ?CarbonInterface $expiresAt): string
    {
        $this->promotionCodes[] = [
            'id' => $id = 'promo_'.(count($this->promotionCodes) + 1), 'code' => $code, 'redeemed' => 0, 'max' => $maxRedemptions, 'expires_at' => $expiresAt, 'active' => true,
            'discount' => PromoCode::describe($discount['percent_off'] ?? null, $discount['amount_off'] ?? null, $discount['duration'], $discount['duration_in_months'] ?? null),
        ];

        return $id;
    }

    public function deactivatePromotionCode(string $id): void
    {
        foreach ($this->promotionCodes as &$promo) {
            $promo['id'] === $id && $promo['active'] = false;
        }
    }

    public function health(): array
    {
        return $this->health;
    }
}
