<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected FixedAssetService $fixedAssetService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Rowing Club',
            'code' => 'rowing',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'River Rowing Club',
            'slug' => 'river-rowing-club',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
        $this->fixedAssetService = app(FixedAssetService::class);
        $this->accountingService->seedDefaultAccounts($this->club);
    }

    public function test_registering_an_asset_linked_to_a_bill_reclassifies_the_expense(): void
    {
        $bill = $this->accountingService->createVendorBill($this->club, [
            'vendor_name' => 'Rowing Boat Supplies Ltd',
            'category' => 'Equipment',
            'amount' => 5000.00,
            'due_date' => '2026-10-01',
        ]);
        $this->accountingService->markBillAsPaid($bill, $this->adminUser);

        $expenseAcc = Account::where('club_id', $this->club->id)->where('code', '5000')->firstOrFail();
        $this->assertEquals(5000.00, $expenseAcc->fresh()->balance);

        $asset = $this->fixedAssetService->registerAsset($this->club, [
            'name' => 'Racing Eight (Boat)',
            'category' => 'Boats',
            'purchase_date' => '2026-09-01',
            'purchase_cost' => 5000.00,
            'bill_id' => $bill->id,
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 10,
            'salvage_value' => 500.00,
        ]);

        $this->assertDatabaseHas('accounting_fixed_assets', [
            'id' => $asset->id,
            'name' => 'Racing Eight (Boat)',
            'purchase_cost' => 5000.00,
        ]);

        // The original expense classification is reclassified into the fixed asset.
        $this->assertEquals(0.00, $expenseAcc->fresh()->balance);

        $assetAcc = Account::where('club_id', $this->club->id)->where('code', '1500')->firstOrFail();
        $this->assertEquals(5000.00, $assetAcc->fresh()->balance);
        $this->assertEquals(5000.00, $asset->net_book_value);
    }

    public function test_registering_an_asset_without_a_bill_posts_opening_value_against_retained_earnings(): void
    {
        $asset = $this->fixedAssetService->registerAsset($this->club, [
            'name' => 'Clubhouse Building',
            'purchase_date' => '2010-01-01',
            'purchase_cost' => 100000.00,
            'depreciation_method' => 'none',
            'useful_life_years' => 50,
        ]);

        // Retained Earnings is an equity account (credit-normal), so crediting it
        // for the asset's opening value shows as a positive balance.
        $retainedAcc = Account::where('club_id', $this->club->id)->where('code', '3000')->firstOrFail();
        $this->assertEquals(100000.00, $retainedAcc->fresh()->balance);

        $assetAcc = Account::where('club_id', $this->club->id)->where('code', '1500')->firstOrFail();
        $this->assertEquals(100000.00, $assetAcc->fresh()->balance);
        $this->assertEquals(100000.00, $asset->net_book_value);
    }

    public function test_straight_line_depreciation_run_is_idempotent_per_period(): void
    {
        $asset = $this->fixedAssetService->registerAsset($this->club, [
            'name' => 'Minibus',
            'purchase_date' => '2026-01-01',
            'purchase_cost' => 10000.00,
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 5,
            'salvage_value' => 1000.00,
        ]);

        $result = $this->fixedAssetService->runDepreciation($this->club, '2026');
        $this->assertEquals(1, $result['created_count']);
        $this->assertEquals(1800.00, $result['total_depreciation']); // (10000-1000)/5

        $asset->refresh();
        $this->assertEquals(1800.00, $asset->accumulated_depreciation);
        $this->assertEquals(8200.00, $asset->net_book_value);

        $depreciationAcc = Account::where('club_id', $this->club->id)->where('code', '5900')->firstOrFail();
        $this->assertEquals(1800.00, $depreciationAcc->fresh()->balance);

        // Running the same period again must not double-post.
        $repeat = $this->fixedAssetService->runDepreciation($this->club, '2026');
        $this->assertEquals(0, $repeat['created_count']);
        $this->assertEquals(1, $repeat['skipped_count']);
        $this->assertEquals(1800.00, $asset->fresh()->accumulated_depreciation);

        // A second year's run adds to the accumulated total.
        $this->fixedAssetService->runDepreciation($this->club, '2027');
        $this->assertEquals(3600.00, $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(6400.00, $asset->fresh()->net_book_value);
    }

    public function test_disposing_an_asset_posts_a_balanced_gain_on_disposal(): void
    {
        $asset = $this->fixedAssetService->registerAsset($this->club, [
            'name' => 'Old Coaching Launch',
            'purchase_date' => '2020-01-01',
            'purchase_cost' => 4000.00,
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 4,
            'salvage_value' => 0,
        ]);

        $this->fixedAssetService->runDepreciation($this->club, '2020');
        $this->fixedAssetService->runDepreciation($this->club, '2021');
        $this->fixedAssetService->runDepreciation($this->club, '2022');
        // Accumulated depreciation now 3000, net book value 1000.
        $this->assertEquals(1000.00, $asset->fresh()->net_book_value);

        $this->fixedAssetService->disposeAsset($asset, '2026-09-01', 1500.00);

        $asset->refresh();
        $this->assertTrue($asset->isDisposed());
        $this->assertEquals(1500.00, (float) $asset->disposal_proceeds);

        $assetAcc = Account::where('club_id', $this->club->id)->where('code', '1500')->firstOrFail();
        $this->assertEquals(0.00, $assetAcc->fresh()->balance);

        $accumulatedAcc = Account::where('club_id', $this->club->id)->where('code', '1510')->firstOrFail();
        $this->assertEquals(0.00, $accumulatedAcc->fresh()->balance);

        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $this->assertEquals(1500.00, $bankAcc->fresh()->balance);

        // Proceeds (1500) + accumulated depreciation (3000) - cost (4000) = 500 gain.
        $gainLossAcc = Account::where('club_id', $this->club->id)->where('code', '4900')->firstOrFail();
        $this->assertEquals(500.00, $gainLossAcc->fresh()->balance);
    }

    public function test_fixed_asset_actions_are_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.fixed_assets.store', $this->club->slug), [
                'name' => 'Kayak',
                'purchase_date' => '2026-09-01',
                'purchase_cost' => 500.00,
                'depreciation_method' => 'none',
                'useful_life_years' => 5,
            ])
            ->assertForbidden();
    }
}
