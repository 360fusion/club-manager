<?php

namespace App\Services;

use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetDepreciationEntry;
use App\Models\Club;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fixed asset register and depreciation, kept separate from AccountingService since
 * it's an optional add-on most clubs never touch (few own boats/regalia/buildings).
 */
class FixedAssetService
{
    public function __construct(protected AccountingService $accountingService) {}

    /**
     * Register a fixed asset and capitalise its cost onto the balance sheet. If it's
     * linked to a vendor Bill, that cost is reclassified out of the expense account
     * it originally posted against (5000) into Fixed Assets (1500); otherwise it's
     * recorded as an opening asset value against Retained Earnings (3000).
     */
    public function registerAsset(Club $club, array $data): FixedAsset
    {
        return DB::transaction(function () use ($club, $data) {
            $assetAcc = $this->getOrCreateFixedAssetsAccount($club);

            $asset = FixedAsset::create([
                'club_id' => $club->id,
                'name' => $data['name'],
                'category' => $data['category'] ?? 'Equipment',
                'purchase_date' => $data['purchase_date'],
                'purchase_cost' => $data['purchase_cost'],
                'bill_id' => $data['bill_id'] ?? null,
                'depreciation_method' => $data['depreciation_method'] ?? 'straight_line',
                'useful_life_years' => $data['useful_life_years'] ?? 5,
                'salvage_value' => $data['salvage_value'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $cost = (float) $asset->purchase_cost;
            $offsetAcc = $asset->bill_id
                ? $this->accountingService->getAccount($club, '5000')
                : $this->accountingService->getAccount($club, '3000');

            $this->accountingService->postJournalEntry($club, [
                'entry_date' => $asset->purchase_date->format('Y-m-d'),
                'description' => "Fixed Asset Registered: {$asset->name}",
                'source_type' => 'FixedAsset',
                'source_id' => $asset->id,
                'items' => [
                    ['account_id' => $assetAcc->id, 'debit' => $cost, 'credit' => 0, 'memo' => 'Capitalised as fixed asset'],
                    ['account_id' => $offsetAcc->id, 'debit' => 0, 'credit' => $cost, 'memo' => $asset->bill_id ? 'Reclassified from operating expense' : 'Opening asset value'],
                ],
            ]);

            return $asset;
        });
    }

    /**
     * One annual depreciation charge per active asset for the given period label
     * (e.g. '2026'). Idempotent — an asset already depreciated for that period is
     * skipped, matching SubscriptionBillingService::generateAnnualBillingRun's shape.
     *
     * @return array{created_count: int, skipped_count: int, total_depreciation: float}
     */
    public function runDepreciation(Club $club, string $period): array
    {
        $assets = FixedAsset::where('club_id', $club->id)
            ->whereNull('disposal_date')
            ->where('depreciation_method', '!=', 'none')
            ->get();

        $createdCount = 0;
        $skippedCount = 0;
        $totalDepreciation = 0.0;

        DB::transaction(function () use ($club, $assets, $period, &$createdCount, &$skippedCount, &$totalDepreciation) {
            $depreciationAcc = $this->getOrCreateDepreciationExpenseAccount($club);
            $accumulatedAcc = $this->getOrCreateAccumulatedDepreciationAccount($club);

            foreach ($assets as $asset) {
                $exists = FixedAssetDepreciationEntry::where('fixed_asset_id', $asset->id)->where('period', $period)->exists();
                if ($exists) {
                    $skippedCount++;

                    continue;
                }

                $amount = $this->calculatePeriodDepreciation($asset);
                if ($amount <= 0) {
                    $skippedCount++;

                    continue;
                }

                $entry = $this->accountingService->postJournalEntry($club, [
                    'description' => "Depreciation {$period}: {$asset->name}",
                    'source_type' => 'FixedAssetDepreciation',
                    'source_id' => $asset->id,
                    'items' => [
                        ['account_id' => $depreciationAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => $asset->name],
                        ['account_id' => $accumulatedAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => $asset->name],
                    ],
                ]);

                FixedAssetDepreciationEntry::create([
                    'fixed_asset_id' => $asset->id,
                    'club_id' => $club->id,
                    'period' => $period,
                    'amount' => $amount,
                    'journal_entry_id' => $entry->id,
                ]);

                $createdCount++;
                $totalDepreciation += $amount;
            }
        });

        return ['created_count' => $createdCount, 'skipped_count' => $skippedCount, 'total_depreciation' => round($totalDepreciation, 2)];
    }

    /**
     * Dispose of an asset: clears its cost and accumulated depreciation off the
     * balance sheet, records any sale proceeds, and posts the resulting gain or
     * loss so the books stay balanced.
     */
    public function disposeAsset(FixedAsset $asset, string $disposalDate, float $proceeds): void
    {
        if ($asset->isDisposed()) {
            throw new InvalidArgumentException('This asset has already been disposed of.');
        }

        DB::transaction(function () use ($asset, $disposalDate, $proceeds) {
            $club = $asset->club;
            $accumulated = $asset->accumulated_depreciation;
            $cost = (float) $asset->purchase_cost;
            $gainOrLoss = round($proceeds + $accumulated - $cost, 2);

            $asset->update(['disposal_date' => $disposalDate, 'disposal_proceeds' => $proceeds]);

            $bankAcc = $this->accountingService->getAccount($club, '1000');
            $assetAcc = $this->getOrCreateFixedAssetsAccount($club);
            $accumulatedAcc = $this->getOrCreateAccumulatedDepreciationAccount($club);
            $gainLossAcc = $this->getOrCreateGainLossAccount($club);

            $items = [];
            if ($proceeds > 0) {
                $items[] = ['account_id' => $bankAcc->id, 'debit' => $proceeds, 'credit' => 0, 'memo' => 'Disposal proceeds'];
            }
            if ($accumulated > 0) {
                $items[] = ['account_id' => $accumulatedAcc->id, 'debit' => $accumulated, 'credit' => 0, 'memo' => 'Clear accumulated depreciation'];
            }
            $items[] = ['account_id' => $assetAcc->id, 'debit' => 0, 'credit' => $cost, 'memo' => 'Remove asset at cost'];

            if ($gainOrLoss > 0) {
                $items[] = ['account_id' => $gainLossAcc->id, 'debit' => 0, 'credit' => $gainOrLoss, 'memo' => 'Gain on disposal'];
            } elseif ($gainOrLoss < 0) {
                $items[] = ['account_id' => $gainLossAcc->id, 'debit' => abs($gainOrLoss), 'credit' => 0, 'memo' => 'Loss on disposal'];
            }

            $this->accountingService->postJournalEntry($club, [
                'entry_date' => $disposalDate,
                'description' => "Fixed Asset Disposed: {$asset->name}",
                'source_type' => 'FixedAssetDisposal',
                'source_id' => $asset->id,
                'items' => $items,
            ]);
        });
    }

    private function calculatePeriodDepreciation(FixedAsset $asset): float
    {
        $depreciableBase = (float) $asset->purchase_cost - (float) $asset->salvage_value;
        $netBookValue = $asset->net_book_value;

        if ($netBookValue <= (float) $asset->salvage_value) {
            return 0.0;
        }

        if ($asset->depreciation_method === 'reducing_balance') {
            $rate = $asset->useful_life_years > 0 ? 2 / $asset->useful_life_years : 0;
            $amount = $netBookValue * $rate;
        } else {
            $amount = $asset->useful_life_years > 0 ? $depreciableBase / $asset->useful_life_years : 0;
        }

        // Never depreciate below salvage value.
        $amount = min($amount, $netBookValue - (float) $asset->salvage_value);

        return round(max($amount, 0), 2);
    }

    private function getOrCreateFixedAssetsAccount(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '1500'],
            ['name' => 'Fixed Assets', 'type' => 'asset', 'currency' => $club->currencyCode(), 'is_active' => true]
        );
    }

    private function getOrCreateAccumulatedDepreciationAccount(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '1510'],
            ['name' => 'Accumulated Depreciation', 'type' => 'asset', 'currency' => $club->currencyCode(), 'is_active' => true]
        );
    }

    private function getOrCreateDepreciationExpenseAccount(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '5900'],
            ['name' => 'Depreciation Expense', 'type' => 'expense', 'currency' => $club->currencyCode(), 'is_active' => true]
        );
    }

    private function getOrCreateGainLossAccount(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => '4900'],
            ['name' => 'Gain/Loss on Disposal of Assets', 'type' => 'revenue', 'currency' => $club->currencyCode(), 'is_active' => true]
        );
    }
}
