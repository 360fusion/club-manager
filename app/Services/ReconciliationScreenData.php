<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankMatchRule;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Models\Club;
use App\Support\Currencies;

/**
 * Everything the accounting screen's reconciliation tab shows: unmatched and reconciled bank lines with suggested matches,
 * imports, unpaid subscriptions, the statement and account histories, Gift Aid figures and remembered match rules.
 */
class ReconciliationScreenData
{
    /** The statement and account history lists show the newest rows only, so the page does not grow with every bank line ever imported. */
    public const HISTORY_ROW_LIMIT = 500;

    /**
     * @return array<string, mixed>
     */
    public function build(Club $club): array
    {
        $matcher = app(BankReconciliationMatcherService::class);
        $unmatchedTxModels = BankTransaction::where('club_id', $club->id)
            ->where('status', 'unmatched')
            ->orderBy('transaction_date', 'asc')
            ->get();

        $unmatchedTransactions = $unmatchedTxModels->map(function ($tx) use ($matcher, $club) {
            $suggestions = $matcher->suggestMatches($tx);

            return [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'formatted_amount' => Currencies::format((float) $tx->amount, $club),
                'status' => $tx->status->value ?? (string) $tx->status,
                'suggested_matches' => array_map(function ($m) {
                    return [
                        'match_type' => $m['match_type'],
                        'target_id' => $m['target_id'],
                        'target_title' => $m['target_title'],
                        'target_amount' => (float) $m['target_amount'],
                        'confidence_score' => $m['confidence_score'],
                        'confidence_level' => $m['confidence_level'],
                        'match_reason' => $m['match_reason'],
                        'nominal_code' => $m['nominal_code'] ?? null,
                        'remembered' => $m['remembered'] ?? false,
                    ];
                }, $suggestions),
            ];
        });

        $reconciledTransactions = BankTransaction::where('club_id', $club->id)
            ->where('status', 'reconciled')
            ->orderByDesc('updated_at')
            ->take(30)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'formatted_amount' => Currencies::format((float) $tx->amount, $club),
                'status' => $tx->status->value ?? (string) $tx->status,
                'updated_at' => $tx->updated_at->format('d M Y H:i'),
            ]);

        $bankImports = BankImport::where('club_id', $club->id)
            ->orderByDesc('created_at')
            ->take(100)
            ->get()
            ->map(fn ($imp) => [
                'id' => $imp->id,
                'bank_account_id' => $imp->bank_account_id ?? null,
                'filename' => $imp->filename,
                'account_number' => $imp->account_number,
                'sort_code' => $imp->sort_code,
                'total_lines' => $imp->total_lines,
                'total_amount' => (float) $imp->total_amount,
                'formatted_total' => Currencies::format((float) $imp->total_amount, $club),
                'created_at' => $imp->created_at->format('d M Y H:i'),
            ]);

        $unpaidSubscriptions = MemberSubscription::where('club_id', $club->id)
            ->unpaid()
            ->with('member')
            ->get()
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'invoice_reference' => $sub->invoice_reference,
                'member_name' => $sub->member ? $sub->member->full_name : 'Unknown Member',
                'amount_due' => (float) $sub->balance_due,
                'formatted_amount' => Currencies::format((float) $sub->balance_due, $club),
            ]);

        $allStatementLines = BankTransaction::where('club_id', $club->id)
            ->orderByDesc('transaction_date')
            ->take(self::HISTORY_ROW_LIMIT)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'type' => $tx->amount < 0 ? 'Debit' : 'Credit',
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference ?: ($tx->amount < 0 ? 'DEBIT' : 'CREDIT'),
                'amount' => (float) $tx->amount,
                'spent' => $tx->amount < 0 ? Currencies::format(abs((float) $tx->amount), $club) : '',
                'received' => $tx->amount > 0 ? Currencies::format((float) $tx->amount, $club) : '',
                'source' => 'Bank Feed',
                'status' => match (strtolower($tx->status->value ?? (string) $tx->status)) {
                    'matched', 'reconciled' => 'Reconciled',
                    'ignored' => 'Deleted',
                    default => 'Unreconciled',
                },
            ]);

        $accountTransactions = BankTransaction::where('club_id', $club->id)
            ->whereIn('status', ['reconciled', 'matched'])
            ->orderByDesc('updated_at')
            ->take(self::HISTORY_ROW_LIMIT)
            ->get()
            ->map(fn ($tx) => [
                'id' => 'tx_'.$tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'type' => $tx->amount < 0 ? 'Spend Money' : 'Receive Money',
                'description' => $tx->raw_description,
                'reference' => $tx->reference ?: 'SYSTEM-REF',
                'amount' => (float) $tx->amount,
                'spent' => $tx->amount < 0 ? Currencies::format(abs((float) $tx->amount), $club) : '',
                'received' => $tx->amount > 0 ? Currencies::format((float) $tx->amount, $club) : '',
                'status' => 'Reconciled',
            ])
            ->concat(
                $unpaidSubscriptions->map(fn ($sub) => [
                    'id' => 'sub_'.$sub['id'],
                    'bank_account_id' => null,
                    'transaction_date' => date('d M Y'),
                    'type' => 'Invoice Payment',
                    'description' => 'Dues: '.$sub['member_name'],
                    'reference' => $sub['invoice_reference'],
                    'amount' => (float) $sub['amount_due'],
                    'spent' => '',
                    'received' => Currencies::format((float) $sub['amount_due'], $club),
                    'status' => 'Unreconciled',
                ])
            )->values();

        $giftAidService = app(ReliefChestReconciliationService::class);
        $giftAidSummary = $giftAidService->getGiftAidSummary($club);
        $reconciledDonationsData = $giftAidService->getReconciledDonations($club);

        $reconciliation = [
            'unmatched_transactions' => $unmatchedTransactions,
            'reconciled_transactions' => $reconciledTransactions,
            'statement_lines' => $allStatementLines,
            'account_transactions' => $accountTransactions,
            'bank_imports' => $bankImports,
            'unpaid_subscriptions' => $unpaidSubscriptions,
            'gift_aid_summary' => $giftAidSummary,
            'reconciled_donations' => $reconciledDonationsData,
            'match_rules' => BankMatchRule::where('club_id', $club->id)->orderByDesc('hit_count')->orderBy('description_pattern')->take(100)->get()->map(fn ($r) => [
                'id' => $r->id,
                'pattern' => $r->description_pattern,
                'match_type' => $r->match_type,
                'nominal_code' => $r->nominal_code,
                'hit_count' => $r->hit_count,
            ])->all(),
        ];

        return $reconciliation;
    }
}
