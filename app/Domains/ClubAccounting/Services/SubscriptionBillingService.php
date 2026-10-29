<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Models\Club;
use App\Support\Currencies;
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
            ->subscribing()
            ->with(['subscriptionTier'])
            ->get();
        $defaultTier = SubscriptionTier::where('club_id', $clubId)->active()->first();

        $createdCount = 0;
        $skippedCount = 0;
        $noRateCount = 0;
        $totalBilled = 0.0;

        DB::transaction(function () use ($activeMembers, $clubId, $billingYear, $effectiveDueDate, $defaultTier, &$createdCount, &$skippedCount, &$noRateCount, &$totalBilled) {
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
                } elseif ($defaultTier) {
                    $amount = (float) $defaultTier->annual_amount;
                } else {
                    // No tier, no override and no tier set up: never invent an amount.
                    $noRateCount++;

                    continue;
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
            'no_rate_count' => $noRateCount,
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
                $paymentLog = sprintf("\nPayment of %s%.2f recorded on %s (Ref: %s)", Currencies::symbol(Currencies::codeForClubId($subscription->club_id)), $amount, Carbon::now()->format('Y-m-d'), $reference ?: 'Direct');
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
     * Unpaid subscriptions more than $graceDays past due, without changing anything.
     */
    public function overdueSubscriptions(Club|int $club, int $graceDays = 90): Collection
    {
        return MemberSubscription::where('club_id', $club instanceof Club ? $club->id : $club)
            ->unpaid()
            ->where('due_date', '<', Carbon::now()->subDays($graceDays))
            ->with(['member', 'tier'])
            ->get();
    }

    /**
     * Audit unpaid subscriptions under UGLE Rule 181 arrears policy, marking each overdue one with an arrears warning.
     */
    public function checkRule181Arrears(Club|int $club, int $graceDays = 90): Collection
    {
        $overdueSubscriptions = $this->overdueSubscriptions($club, $graceDays);

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
