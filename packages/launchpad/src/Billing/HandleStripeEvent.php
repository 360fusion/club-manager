<?php

namespace Launchpad\Billing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\WebhookReceived;
use Launchpad\Models\StripeEvent;
use Launchpad\Models\Workspace;
use Launchpad\Notifications\PaymentFailedNotification;
use Launchpad\Notifications\TrialEndingNotification;
use Launchpad\Webhooks\Webhooks;

/**
 * What the app does of its own when Stripe tells it something (Cashier keeps the subscription rows in step by itself). Stripe sends an event
 * more than once; each is recorded by its id and handled once.
 */
class HandleStripeEvent
{
    public function handle(WebhookReceived $event): void
    {
        $id = $event->payload['id'] ?? null;
        $type = (string) ($event->payload['type'] ?? '');

        if (! is_string($id)) {
            return;
        }

        $first = DB::transaction(function () use ($id, $type) {
            $row = StripeEvent::firstOrCreate(['stripe_event_id' => $id], ['type' => $type]);

            return $row->wasRecentlyCreated;
        });

        if (! $first) {
            return;
        }

        if ($type === 'invoice.payment_failed') {
            $this->paymentFailed($event->payload['data']['object'] ?? []);
        }

        if ($type === 'customer.subscription.trial_will_end') {
            $this->trialEnding($event->payload['data']['object'] ?? []);
        }

        if (in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $this->subscriptionChanged($type, $event->payload['data']['object'] ?? []);
        }

        StripeEvent::where('stripe_event_id', $id)->update(['processed_at' => now()]);
    }

    /** Stripe sends this three days before a trial ends: the owner is told by mail and in the app that the card will be charged */
    private function trialEnding(array $subscription): void
    {
        $customer = $subscription['customer'] ?? null;
        $workspace = $customer ? Workspace::where('stripe_id', $customer)->first() : null;
        $end = isset($subscription['trial_end']) ? Carbon::createFromTimestamp((int) $subscription['trial_end']) : null;

        if ($workspace && $end && $workspace->owner) {
            $workspace->owner->notify(new TrialEndingNotification($workspace, $end));
        }
    }

    /**
     * Tells the workspace's webhook endpoints. What is sent comes from Stripe's event, not from our subscription rows, which Cashier has not
     * updated yet when this runs.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function subscriptionChanged(string $type, array $subscription): void
    {
        $customer = $subscription['customer'] ?? null;
        $workspace = $customer ? Workspace::where('stripe_id', $customer)->first() : null;

        if ($workspace === null) {
            return;
        }

        $match = app(PlanCatalog::class)->byPrice($subscription['items']['data'][0]['price']['id'] ?? null);

        Webhooks::dispatch($workspace, 'subscription.changed', [
            'workspace' => $workspace->slug,
            'change' => str_replace('customer.subscription.', '', $type),
            'status' => $subscription['status'] ?? null,
            'plan' => $match[0]->key ?? null,
            'period' => $match[1] ?? null,
            'cancel_at_period_end' => (bool) ($subscription['cancel_at_period_end'] ?? false),
        ]);
    }

    /** @param  array<string, mixed>  $invoice */
    private function paymentFailed(array $invoice): void
    {
        $customer = $invoice['customer'] ?? null;
        $workspace = $customer ? Workspace::where('stripe_id', $customer)->first() : null;

        $workspace?->owner?->notify(new PaymentFailedNotification($workspace));
    }
}
