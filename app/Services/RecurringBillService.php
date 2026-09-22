<?php

namespace App\Services;

use App\Models\Accounting\RecurringBillTemplate;
use App\Models\Club;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Calendar-driven vendor bills (insurance, hall hire, software subscriptions) —
 * generates a real Bill from a template once its next_run_date arrives, then
 * advances the template's own next_run_date, which is what keeps this idempotent:
 * a second run on the same day finds nothing due. Same "thin command + service
 * owns the due-check" shape as SendWeeklyDigestCommand/WeeklyUpdateDigestService.
 */
class RecurringBillService
{
    public function __construct(protected AccountingService $accountingService) {}

    public function isDue(RecurringBillTemplate $template, ?Carbon $asOf = null): bool
    {
        $asOf ??= now();

        return $template->is_active && $template->next_run_date->lessThanOrEqualTo($asOf->copy()->startOfDay());
    }

    /**
     * Generate a Bill for every due template, optionally scoped to one club (used by
     * the console command, which runs across every club).
     *
     * @return array{created_count: int, total_billed: float}
     */
    public function generateDueBills(?Club $club = null): array
    {
        $query = RecurringBillTemplate::where('is_active', true)->where('next_run_date', '<=', now()->toDateString());
        if ($club) {
            $query->where('club_id', $club->id);
        }

        $createdCount = 0;
        $totalBilled = 0.0;

        foreach ($query->get() as $template) {
            DB::transaction(function () use ($template, &$createdCount, &$totalBilled) {
                $templateClub = $template->club;

                $this->accountingService->createVendorBill($templateClub, [
                    'vendor_name' => $template->vendor_name,
                    'category' => $template->category,
                    'amount' => (float) $template->amount,
                    'due_date' => $template->next_run_date->copy()->addDays(14)->format('Y-m-d'),
                    'notes' => trim(($template->notes ?? '').' (recurring: '.$template->frequency.')'),
                ]);

                $template->update(['next_run_date' => $this->nextRunAfter($template->next_run_date, $template->frequency)]);

                $createdCount++;
                $totalBilled += (float) $template->amount;
            });
        }

        return ['created_count' => $createdCount, 'total_billed' => round($totalBilled, 2)];
    }

    private function nextRunAfter(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'annually' => $date->copy()->addYearNoOverflow(),
            default => throw new InvalidArgumentException("Unknown recurrence frequency: {$frequency}"),
        };
    }
}
