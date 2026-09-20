<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use Carbon\Carbon;

class ReliefChestReconciliationService
{
    /**
     * Get Gift Aid reclaimable summary and MCF Relief Chest position.
     */
    public function getGiftAidSummary(Club|int $club): array
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);
        $collections = CharityCollection::where('club_id', $clubObj->id)->get();

        $totalEligibleDonations = 0.0;
        $totalGiftAidReclaimed = 0.0;
        $pendingGiftAidAmount = 0.0;
        $pendingClaimCount = 0;

        foreach ($collections as $col) {
            $amount = (float)$col->total_amount;
            if ($col->is_gift_aid_eligible) {
                $totalEligibleDonations += $amount;
                $giftAidTax = $col->gift_aid_amount > 0 ? (float)$col->gift_aid_amount : round($amount * 0.25, 2);

                if ($col->gift_aid_status === 'reconciled') {
                    $totalGiftAidReclaimed += $giftAidTax;
                } else {
                    $pendingGiftAidAmount += $giftAidTax;
                    $pendingClaimCount++;
                }
            }
        }

        $festivalTarget = FestivalTarget::where('club_id', $clubObj->id)->first();
        $grantsTotal = CharityGrant::where('club_id', $clubObj->id)
            ->where('approval_status', 'disbursed')
            ->sum('amount');

        $netChestBalance = ($totalEligibleDonations + $totalGiftAidReclaimed) - (float)$grantsTotal;

        return [
            'relief_chest_ref' => $festivalTarget?->relief_chest_ref ?: 'E1418',
            'festival_name' => $festivalTarget?->festival_name ?: 'Provincial Charity Festival',
            'total_eligible_donations' => $totalEligibleDonations,
            'formatted_total_eligible' => '£' . number_format($totalEligibleDonations, 2),
            'total_gift_aid_reclaimed' => $totalGiftAidReclaimed,
            'formatted_gift_aid_reclaimed' => '£' . number_format($totalGiftAidReclaimed, 2),
            'pending_gift_aid_claim' => $pendingGiftAidAmount,
            'formatted_pending_gift_aid' => '£' . number_format($pendingGiftAidAmount, 2),
            'pending_claim_count' => $pendingClaimCount,
            'grants_disbursed_total' => (float)$grantsTotal,
            'formatted_grants_disbursed' => '£' . number_format((float)$grantsTotal, 2),
            'net_relief_chest_balance' => $netChestBalance,
            'formatted_net_relief_chest_balance' => '£' . number_format($netChestBalance, 2),
        ];
    }

    /**
     * Auto-scan and reconcile staged bank transactions matching Gift Aid Recovery or Relief Chest Payouts.
     */
    public function autoReconcileGiftAidAndReliefChest(Club|int $club): array
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);
        $accountingService = app(\App\Services\AccountingService::class);
        $accountingService->seedDefaultAccounts($clubObj);

        $bankAcc = Account::where('club_id', $clubObj->id)->where('code', '1000')->first();
        $giftAidAcc = Account::firstOrCreate(
            ['club_id' => $clubObj->id, 'code' => '4310'],
            ['name' => 'Gift Aid Tax Reclaim Income', 'type' => 'revenue', 'currency' => 'GBP', 'is_active' => true]
        );

        $charityAcc = Account::firstOrCreate(
            ['club_id' => $clubObj->id, 'code' => '4300'],
            ['name' => 'Relief Chest & Alms Contributions', 'type' => 'revenue', 'currency' => 'GBP', 'is_active' => true]
        );

        $unmatchedTransactions = BankTransaction::where('club_id', $clubObj->id)
            ->where('status', BankTransactionStatus::Unmatched->value)
            ->get();

        $reconciledCount = 0;
        $totalAmountReconciled = 0.0;

        foreach ($unmatchedTransactions as $tx) {
            $desc = strtolower($tx->raw_description . ' ' . ($tx->reference ?? ''));
            $amount = (float)$tx->amount;

            if ($amount <= 0) continue; // Only incoming credits for Gift Aid / Relief Chest deposits

            $isGiftAid = preg_match('/(gift\s*aid|hmrc\s*reclaim|hmrc\s*tax|giftaid)/i', $desc);
            $isReliefChest = preg_match('/(relief\s*chest|mcf|tlc\s*chest|provincial\s*relief)/i', $desc);

            if ($isGiftAid || $isReliefChest) {
                $targetAcc = $isGiftAid ? $giftAidAcc : $charityAcc;
                $txDate = $tx->transaction_date ? Carbon::parse($tx->transaction_date)->format('Y-m-d') : date('Y-m-d');
                $memo = $tx->raw_description ?: ($isGiftAid ? 'HMRC Gift Aid Recovery' : 'MCF Relief Chest Payout');

                // Check double-entry journal entry duplication guard
                $alreadyPosted = JournalEntry::where('club_id', $clubObj->id)
                    ->where('source_type', 'bank_transaction')
                    ->where('source_id', $tx->id)
                    ->exists();

                if ($bankAcc && $targetAcc && ! $alreadyPosted) {
                    $accountingService->postJournalEntry($clubObj, [
                        'entry_date' => $txDate,
                        'reference_number' => 'GA-RECON-' . $tx->id,
                        'description' => "Automated Gift Aid / Relief Chest Match: {$memo}",
                        'source_type' => 'bank_transaction',
                        'source_id' => $tx->id,
                        'items' => [
                            ['account_id' => $bankAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => $memo],
                            ['account_id' => $targetAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => $memo],
                        ],
                    ]);
                }

                // Update bank transaction to Matched
                $tx->update([
                    'status' => BankTransactionStatus::Matched,
                    'reference' => ($tx->reference ? $tx->reference . ' ' : '') . '[Gift Aid / Relief Chest Reconciled]',
                ]);

                // Update matching pending charity collection status to reconciled if applicable
                CharityCollection::where('club_id', $clubObj->id)
                    ->where('gift_aid_status', 'pending')
                    ->whereNull('bank_transaction_id')
                    ->take(1)
                    ->update([
                        'gift_aid_status' => 'reconciled',
                        'gift_aid_reconciled_at' => now(),
                        'bank_transaction_id' => $tx->id,
                    ]);

                $reconciledCount++;
                $totalAmountReconciled += $amount;
            }
        }

        return [
            'success' => true,
            'reconciled_count' => $reconciledCount,
            'total_amount' => $totalAmountReconciled,
            'formatted_total_amount' => '£' . number_format($totalAmountReconciled, 2),
        ];
    }

    /**
     * Generate HMRC Gift Aid CSV claim schedule for export.
     */
    public function generateHmrcGiftAidScheduleCsv(Club|int $club): string
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);
        $collections = CharityCollection::where('club_id', $clubObj->id)
            ->where('is_gift_aid_eligible', true)
            ->with(['countedBy', 'witnessedBy'])
            ->get();

        $festivalTarget = FestivalTarget::where('club_id', $clubObj->id)->first();
        $chestRef = $festivalTarget?->relief_chest_ref ?: 'E1418';

        $csv = "Relief Chest Ref,Collection Date,Collection Type,Donation Amount (£),Gift Aid Reclaim (25%) (£),Status,Counter,Witness\n";

        foreach ($collections as $col) {
            $date = $col->created_at ? $col->created_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            $type = $col->collection_type?->label() ?? 'Alms Plate';
            $donation = number_format((float)$col->total_amount, 2, '.', '');
            $giftAid = number_format($col->calculated_gift_aid, 2, '.', '');
            $status = ucfirst($col->gift_aid_status ?? 'pending');
            $counter = $col->countedBy?->full_name ?? 'Charity Steward';
            $witness = $col->witnessedBy?->full_name ?? 'Assistant DC';

            $csv .= "\"{$chestRef}\",\"{$date}\",\"{$type}\",{$donation},{$giftAid},\"{$status}\",\"{$counter}\",\"{$witness}\"\n";
        }

        return $csv;
    }

    /**
     * Get filterable list of reconciled & eligible donations with donor & Gift Aid details.
     */
    public function getReconciledDonations(Club|int $club, array $filters = []): array
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);
        $query = CharityCollection::where('club_id', $clubObj->id)
            ->with(['countedBy', 'witnessedBy', 'donor', 'bankTransaction']);

        // Date Range Filtering
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Person / Donor Filtering
        if (! empty($filters['person_id'])) {
            $personId = (int)$filters['person_id'];
            $query->where(function ($q) use ($personId) {
                $q->where('donor_member_id', $personId)
                  ->orWhere('counted_by_member_id', $personId)
                  ->orWhere('witnessed_by_member_id', $personId);
            });
        } elseif (! empty($filters['person_search'])) {
            $pSearch = strtolower($filters['person_search']);
            $query->where(function ($q) use ($pSearch) {
                $q->where('donor_name', 'like', "%{$pSearch}%")
                  ->orWhereHas('donor', fn ($dq) => $dq->where('first_name', 'like', "%{$pSearch}%")->orWhere('last_name', 'like', "%{$pSearch}%"))
                  ->orWhereHas('countedBy', fn ($cq) => $cq->where('first_name', 'like', "%{$pSearch}%")->orWhere('last_name', 'like', "%{$pSearch}%"));
            });
        }

        // Gift Aid Status Filtering
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'pending') {
                $query->where(function ($q) {
                    $q->where('gift_aid_status', 'pending')->orWhere('gift_aid_status', 'unclaimed');
                });
            } else {
                $query->where('gift_aid_status', $filters['status']);
            }
        }

        // Keyword Search Filtering
        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhere('collection_type', 'like', "%{$search}%")
                  ->orWhere('donor_name', 'like', "%{$search}%")
                  ->orWhereHas('bankTransaction', fn ($bq) => $bq->where('raw_description', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%"));
            });
        }

        $collections = $query->orderByDesc('created_at')->get();

        $totalDonationsSum = 0.0;
        $totalGiftAidSum = 0.0;
        $reconciledCount = 0;

        $mappedCollections = $collections->map(function ($col) use (&$totalDonationsSum, &$totalGiftAidSum, &$reconciledCount) {
            $totalAmount = (float)$col->total_amount;
            $giftAid = $col->is_gift_aid_eligible ? ($col->gift_aid_amount > 0 ? (float)$col->gift_aid_amount : round($totalAmount * 0.25, 2)) : 0.0;

            $totalDonationsSum += $totalAmount;
            $totalGiftAidSum += $giftAid;
            if ($col->gift_aid_status === 'reconciled') {
                $reconciledCount++;
            }

            return [
                'id' => $col->id,
                'created_at' => $col->created_at ? $col->created_at->format('d M Y') : date('d M Y'),
                'raw_date' => $col->created_at ? $col->created_at->format('Y-m-d') : date('Y-m-d'),
                'collection_type' => $col->collection_type?->label() ?? 'Alms Plate / Donation',
                'donor_name' => $col->donor_display_name,
                'donor_id' => $col->donor_member_id,
                'cash_amount' => (float)$col->cash_amount,
                'cheque_amount' => (float)$col->cheque_amount,
                'total_amount' => $totalAmount,
                'formatted_total' => '£' . number_format($totalAmount, 2),
                'is_gift_aid_eligible' => (bool)$col->is_gift_aid_eligible,
                'gift_aid_amount' => $giftAid,
                'formatted_gift_aid' => '£' . number_format($giftAid, 2),
                'gift_aid_status' => $col->gift_aid_status ?: 'pending',
                'gift_aid_reconciled_at' => $col->gift_aid_reconciled_at ? $col->gift_aid_reconciled_at->format('d M Y H:i') : null,
                'bank_transaction' => $col->bankTransaction ? [
                    'id' => $col->bankTransaction->id,
                    'transaction_date' => $col->bankTransaction->transaction_date ? $col->bankTransaction->transaction_date->format('d M Y') : null,
                    'raw_description' => $col->bankTransaction->raw_description,
                    'reference' => $col->bankTransaction->reference,
                    'amount' => (float)$col->bankTransaction->amount,
                    'formatted_amount' => '£' . number_format((float)$col->bankTransaction->amount, 2),
                ] : null,
                'counted_by' => $col->countedBy?->full_name ?? 'Charity Steward',
                'witnessed_by' => $col->witnessedBy?->full_name ?? 'Assistant DC',
                'notes' => $col->notes,
            ];
        });

        // Load members for filtering dropdown
        $members = \App\Domains\ClubAccounting\Models\Member::where('club_id', $clubObj->id)
            ->orderBy('first_name')
            ->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->full_name]);

        return [
            'collections' => $mappedCollections,
            'totals' => [
                'total_donations' => $totalDonationsSum,
                'formatted_total_donations' => '£' . number_format($totalDonationsSum, 2),
                'total_gift_aid' => $totalGiftAidSum,
                'formatted_total_gift_aid' => '£' . number_format($totalGiftAidSum, 2),
                'reconciled_count' => $reconciledCount,
                'total_count' => $collections->count(),
            ],
            'members' => $members,
            'filters' => array_merge([
                'date_from' => '',
                'date_to' => '',
                'person_id' => '',
                'person_search' => '',
                'status' => 'all',
                'search' => '',
            ], $filters),
        ];
    }
}
