<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingAdmin\RunFixedAssetDepreciationRequest;
use App\Http\Requests\AccountingAdmin\StoreFixedAssetRequest;
use App\Http\Requests\AccountingAdmin\StoreRecurringBillTemplateRequest;
use App\Http\Requests\AccountingAdmin\UpdateBudgetRequest;
use App\Http\Requests\AccountingAsset\DisposeFixedAssetRequest;
use App\Models\Accounting\Bill;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\RecurringBillTemplate;
use App\Services\BudgetService;
use App\Services\FixedAssetService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fixed assets, budgets and recurring bill templates. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingAssetController extends Controller
{
    public function __construct(
        protected FixedAssetService $fixedAssetService,
        protected BudgetService $budgetService
    ) {}

    public function storeFixedAsset(StoreFixedAssetRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        if (! empty($validated['bill_id'])) {
            $bill = Bill::where('club_id', $club->id)->where('id', $validated['bill_id'])->first();
            if (! $bill) {
                return redirect()->back()->withErrors(['bill_id' => 'That bill does not belong to this club.']);
            }
        }

        $asset = $this->fixedAssetService->registerAsset($club, $validated);

        return redirect()->back()->with('success', "Fixed asset '{$asset->name}' registered and capitalised.");
    }

    public function disposeFixedAsset(DisposeFixedAssetRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $asset = FixedAsset::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $this->fixedAssetService->disposeAsset($asset, $validated['disposal_date'], (float) $validated['disposal_proceeds']);

        return redirect()->back()->with('success', "Fixed asset '{$asset->name}' disposed of.");
    }

    public function runFixedAssetDepreciation(RunFixedAssetDepreciationRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $result = $this->fixedAssetService->runDepreciation($club, $validated['period']);

        return redirect()->back()->with('success', "Depreciation run for {$validated['period']}: {$result['created_count']} asset(s) depreciated, {$result['skipped_count']} skipped.");
    }

    public function updateBudget(UpdateBudgetRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $this->budgetService->setBudgetLines($club, (int) $validated['financial_year'], $validated['lines']);

        return redirect()->back()->with('success', "Budget for {$validated['financial_year']} saved.");
    }

    public function storeRecurringBillTemplate(StoreRecurringBillTemplateRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        RecurringBillTemplate::create([
            'club_id' => $club->id,
            'vendor_name' => $validated['vendor_name'],
            'category' => $validated['category'] ?? 'General Expense',
            'amount' => $validated['amount'],
            'frequency' => $validated['frequency'],
            'next_run_date' => $validated['next_run_date'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Recurring bill for {$validated['vendor_name']} scheduled.");
    }

    public function toggleRecurringBillTemplate(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $template = RecurringBillTemplate::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $template->update(['is_active' => ! $template->is_active]);

        return redirect()->back()->with('success', $template->is_active ? 'Recurring bill resumed.' : 'Recurring bill paused.');
    }

    public function destroyRecurringBillTemplate(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        RecurringBillTemplate::where('club_id', $club->id)->where('id', $id)->firstOrFail()->delete();

        return redirect()->back()->with('success', 'Recurring bill schedule removed.');
    }
}
