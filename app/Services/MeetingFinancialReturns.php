<?php

namespace App\Services;

use App\Models\Accounting\Bill;
use App\Models\Accounting\MeetingFinancialReturn;
use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;

/**
 * Posting a lodge meeting's financial return to the ledger, and saving it as a draft first. Split out of AccountingService.
 */
class MeetingFinancialReturns
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Post Meeting Financial Return (Dining Calculator + Charity Collections) to Accounting Ledger
     */
    public function postMeetingFinancialReturn(Club $club, Meeting $meeting, array $data): MeetingFinancialReturn
    {
        return DB::transaction(function () use ($club, $meeting, $data) {
            $diningFee = (float) ($data['dining_fee_per_head'] ?? 0);
            $paidDiners = (int) ($data['paid_diners_count'] ?? 0);
            $waivedDiners = (int) ($data['waived_diners_count'] ?? 0);
            $kitchenCostPerHead = (float) ($data['kitchen_cost_per_head'] ?? 0);
            $kitchenVendor = ! empty($data['kitchen_vendor_name']) ? $data['kitchen_vendor_name'] : 'Kitchen Caterer';

            $raffleAmount = (float) ($data['raffle_amount'] ?? 0);
            $almsAmount = (float) ($data['alms_amount'] ?? 0);
            $donationsAmount = (float) ($data['donations_amount'] ?? 0);
            $bequestAmount = (float) ($data['bequest_amount'] ?? 0);

            $totalMeals = $paidDiners + $waivedDiners;
            $totalDiningRevenue = round($paidDiners * $diningFee, 2);
            $totalKitchenBill = round($totalMeals * $kitchenCostPerHead, 2);
            $netDiningSurplus = round($totalDiningRevenue - $totalKitchenBill, 2);
            $totalCharity = round($raffleAmount + $almsAmount + $donationsAmount + $bequestAmount, 2);
            $netBankDeposit = round($totalDiningRevenue + $totalCharity, 2);

            $returnDate = ! empty($meeting->meeting_date) ? date('Y-m-d', strtotime((string) $meeting->meeting_date)) : date('Y-m-d');

            // 1. Create Kitchen Vendor Bill (A/P)
            $vendorBill = null;
            if ($totalKitchenBill > 0) {
                $vendorBill = $this->accounting->createVendorBill($club, [
                    'vendor_name' => $kitchenVendor,
                    'category' => 'Catering & Food Supplies',
                    'amount' => $totalKitchenBill,
                    'due_date' => $returnDate,
                    'notes' => "Kitchen catering for {$meeting->title} ({$totalMeals} meals @ {$club->currencySymbol()}{$kitchenCostPerHead}/head)",
                ]);
            }

            // 2. Post Journal Entry to General Ledger
            $journalItems = [];
            $bankAcc = $this->accounting->getAccount($club, '1000');
            $diningRevenueAcc = $this->accounting->getAccount($club, '4200');
            $raffleAcc = $this->accounting->getAccount($club, '4300');
            $almsAcc = $this->accounting->getAccount($club, '4400');
            $donationsAcc = $this->accounting->getAccount($club, '4500');

            if ($netBankDeposit > 0) {
                $journalItems[] = ['account_id' => $bankAcc->id, 'debit' => $netBankDeposit, 'credit' => 0, 'memo' => "Meeting Collections Deposit ({$meeting->title})"];
            }

            if ($totalDiningRevenue > 0) {
                $journalItems[] = ['account_id' => $diningRevenueAcc->id, 'debit' => 0, 'credit' => $totalDiningRevenue, 'memo' => "Dining Receipts ({$paidDiners} paid diners)"];
            }
            if ($raffleAmount > 0) {
                $journalItems[] = ['account_id' => $raffleAcc->id, 'debit' => 0, 'credit' => $raffleAmount, 'memo' => 'Raffle Ticket Sales'];
            }
            if ($almsAmount > 0) {
                $journalItems[] = ['account_id' => $almsAcc->id, 'debit' => 0, 'credit' => $almsAmount, 'memo' => 'Alms Box Collection'];
            }
            if ($donationsAmount > 0) {
                $journalItems[] = ['account_id' => $donationsAcc->id, 'debit' => 0, 'credit' => $donationsAmount, 'memo' => 'Meeting Donations'];
            }
            if ($bequestAmount > 0) {
                $journalItems[] = ['account_id' => $donationsAcc->id, 'debit' => 0, 'credit' => $bequestAmount, 'memo' => 'Bequest Funds'];
            }

            $journalEntry = null;
            if (count($journalItems) >= 2) {
                $journalEntry = $this->accounting->postJournalEntry($club, [
                    'description' => "Meeting Financial Return: {$meeting->title} ({$returnDate})",
                    'source_type' => 'MeetingReturn',
                    'source_id' => $meeting->id,
                    'items' => $journalItems,
                ]);
            }

            // 3. Store MeetingFinancialReturn Record
            $financialReturn = MeetingFinancialReturn::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'meeting_id' => $meeting->id,
                ],
                [
                    'return_date' => $returnDate,
                    'dining_fee_per_head' => $diningFee,
                    'paid_diners_count' => $paidDiners,
                    'waived_diners_count' => $waivedDiners,
                    'waived_reason' => $data['waived_reason'] ?? null,
                    'kitchen_cost_per_head' => $kitchenCostPerHead,
                    'kitchen_vendor_name' => $kitchenVendor,
                    'raffle_amount' => $raffleAmount,
                    'alms_amount' => $almsAmount,
                    'donations_amount' => $donationsAmount,
                    'bequest_amount' => $bequestAmount,
                    'total_dining_revenue' => $totalDiningRevenue,
                    'total_kitchen_bill' => $totalKitchenBill,
                    'net_dining_surplus' => $netDiningSurplus,
                    'total_charity_collected' => $totalCharity,
                    'net_bank_deposit' => $netBankDeposit,
                    'vendor_bill_id' => $vendorBill?->id,
                    'journal_entry_id' => $journalEntry?->id,
                    'is_draft' => false,
                    'notes' => $data['notes'] ?? null,
                ]
            );

            return $financialReturn;
        });
    }

    /**
     * Save draft financial return for a meeting without posting to general ledger.
     */
    public function saveMeetingFinancialReturnDraft(Club $club, Meeting $meeting, array $data): MeetingFinancialReturn
    {
        $diningFee = (float) ($data['dining_fee_per_head'] ?? 0);
        $paidDiners = (int) ($data['paid_diners_count'] ?? 0);
        $waivedDiners = (int) ($data['waived_diners_count'] ?? 0);
        $kitchenCostPerHead = (float) ($data['kitchen_cost_per_head'] ?? 0);
        $kitchenVendor = ! empty($data['kitchen_vendor_name']) ? $data['kitchen_vendor_name'] : 'Kitchen Caterer';

        $raffleAmount = (float) ($data['raffle_amount'] ?? 0);
        $almsAmount = (float) ($data['alms_amount'] ?? 0);
        $donationsAmount = (float) ($data['donations_amount'] ?? 0);
        $bequestAmount = (float) ($data['bequest_amount'] ?? 0);

        $totalMeals = $paidDiners + $waivedDiners;
        $totalDiningRevenue = round($paidDiners * $diningFee, 2);
        $totalKitchenBill = round($totalMeals * $kitchenCostPerHead, 2);
        $netDiningSurplus = round($totalDiningRevenue - $totalKitchenBill, 2);
        $totalCharity = round($raffleAmount + $almsAmount + $donationsAmount + $bequestAmount, 2);
        $netBankDeposit = round($totalDiningRevenue + $totalCharity, 2);

        $returnDate = ! empty($data['return_date'])
            ? date('Y-m-d', strtotime((string) $data['return_date']))
            : (! empty($meeting->meeting_date) ? date('Y-m-d', strtotime((string) $meeting->meeting_date)) : date('Y-m-d'));

        return MeetingFinancialReturn::updateOrCreate(
            [
                'club_id' => $club->id,
                'meeting_id' => $meeting->id,
            ],
            [
                'return_date' => $returnDate,
                'dining_fee_per_head' => $diningFee,
                'paid_diners_count' => $paidDiners,
                'waived_diners_count' => $waivedDiners,
                'waived_reason' => $data['waived_reason'] ?? null,
                'kitchen_cost_per_head' => $kitchenCostPerHead,
                'kitchen_vendor_name' => $kitchenVendor,
                'raffle_amount' => $raffleAmount,
                'alms_amount' => $almsAmount,
                'donations_amount' => $donationsAmount,
                'bequest_amount' => $bequestAmount,
                'total_dining_revenue' => $totalDiningRevenue,
                'total_kitchen_bill' => $totalKitchenBill,
                'net_dining_surplus' => $netDiningSurplus,
                'total_charity_collected' => $totalCharity,
                'net_bank_deposit' => $netBankDeposit,
                'is_draft' => true,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }
}
