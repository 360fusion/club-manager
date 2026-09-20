<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Models\Club;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionBillingService
{
    /**
     * Execute annual subscription billing run for all active lodge members.
     */
    public function generateAnnualBillingRun(Club|int $club, int $billingYear, ?Carbon $dueDate = null): array
    {
        $clubId = $club instanceof Club ? $club->id : $club;
        $effectiveDueDate = $dueDate ?? Carbon::create($billingYear, 4, 1);

        $activeMembers = Member::where('club_id', $clubId)
            ->active()
            ->with(['subscriptionTier'])
            ->get();

        $createdCount = 0;
        $skippedCount = 0;
        $totalBilled = 0.0;

        DB::transaction(function () use ($activeMembers, $clubId, $billingYear, $effectiveDueDate, &$createdCount, &$skippedCount, &$totalBilled) {
            foreach ($activeMembers as $member) {
                // Check if invoice already generated for this billing year
                $exists = MemberSubscription::where('member_id', $member->id)
                    ->where('billing_year', $billingYear)
                    ->exists();

                if ($exists) {
                    $skippedCount++;

                    continue;
                }

                // Determine fee amount
                $amount = 0.0;
                if ($member->annual_dues_override !== null) {
                    $amount = (float) $member->annual_dues_override;
                } elseif ($member->subscriptionTier) {
                    $amount = (float) $member->subscriptionTier->annual_amount;
                } elseif ($member->membership_status === MembershipStatus::Honorary) {
                    $amount = 0.0;
                } else {
                    // Default fallback standard dues tier if available
                    $defaultTier = SubscriptionTier::where('club_id', $clubId)->active()->first();
                    $amount = $defaultTier ? (float) $defaultTier->annual_amount : 160.00;
                }

                $status = ($amount == 0.0 || $member->membership_status === MembershipStatus::Honorary)
                    ? SubscriptionStatus::Waived
                    : SubscriptionStatus::Unpaid;

                $invoiceRef = sprintf('INV-%d-M%04d', $billingYear, $member->id);

                MemberSubscription::create([
                    'club_id' => $clubId,
                    'member_id' => $member->id,
                    'tier_id' => $member->subscription_tier_id,
                    'billing_year' => $billingYear,
                    'due_date' => $effectiveDueDate,
                    'amount_due' => $amount,
                    'amount_paid' => 0.00,
                    'status' => $status,
                    'invoice_reference' => $invoiceRef,
                    'notes' => "Annual Subscription Invoice {$billingYear}/".($billingYear + 1),
                ]);

                $createdCount++;
                $totalBilled += $amount;
            }
        });

        return [
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'total_billed' => $totalBilled,
        ];
    }

    /**
     * Record payment received against a member subscription line.
     */
    public function recordPayment(MemberSubscription $subscription, float $amount, ?string $reference = null, ?string $notes = null): MemberSubscription
    {
        return DB::transaction(function () use ($subscription, $amount, $reference, $notes) {
            $newPaid = (float) $subscription->amount_paid + $amount;
            $due = (float) $subscription->amount_due;

            $status = SubscriptionStatus::Paid;
            if ($newPaid < $due) {
                $status = $newPaid > 0 ? SubscriptionStatus::PartiallyPaid : SubscriptionStatus::Unpaid;
            }

            $noteText = $subscription->notes ?? '';
            if ($notes || $reference) {
                $paymentLog = sprintf("\nPayment of £%.2f recorded on %s (Ref: %s)", $amount, Carbon::now()->format('Y-m-d'), $reference ?: 'Direct');
                $noteText .= $paymentLog.($notes ? " - {$notes}" : '');
            }

            $subscription->update([
                'amount_paid' => $newPaid,
                'status' => $status,
                'notes' => trim($noteText),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Update subscription tier amount following a carried committee motion.
     */
    public function updateTierAmountFromMotion(SubscriptionTier $tier, float $newAmount): SubscriptionTier
    {
        $tier->update([
            'annual_amount' => $newAmount,
        ]);

        return $tier->fresh();
    }

    /**
     * Audit unpaid subscriptions under UGLE Rule 181 arrears policy.
     */
    public function checkRule181Arrears(Club|int $club, int $graceDays = 90): Collection
    {
        $clubId = $club instanceof Club ? $club->id : $club;
        $cutoffDate = Carbon::now()->subDays($graceDays);

        $overdueSubscriptions = MemberSubscription::where('club_id', $clubId)
            ->unpaid()
            ->where('due_date', '<', $cutoffDate)
            ->with(['member', 'tier'])
            ->get();

        foreach ($overdueSubscriptions as $sub) {
            if ($sub->status !== SubscriptionStatus::ArrearsWarning) {
                $sub->update([
                    'status' => SubscriptionStatus::ArrearsWarning,
                ]);
            }
        }

        return $overdueSubscriptions;
    }
}
